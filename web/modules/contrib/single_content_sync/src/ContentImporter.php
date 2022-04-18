<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TypedData\TranslatableInterface;

class ContentImporter implements ContentImporterInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The content sync helper.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected $contentSyncHelper;

  /**
   * ContentExporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, ContentSyncHelperInterface $content_sync_helper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->contentSyncHelper = $content_sync_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function doImport(array $content): EntityInterface {
    $storage = $this->entityTypeManager->getStorage($content['entity_type']);
    $definition = $this->entityTypeManager->getDefinition($content['entity_type']);

    // Check if there is an existing entity with the identical uuid.
    $entity = $this->entityRepository->loadEntityByUuid($content['entity_type'], $content['uuid']);

    // If not, create a new instance of the entity.
    if (!$entity) {
      $values = [
        'uuid' => $content['uuid'],
      ];
      if ($bundle_key = $definition->getKey('bundle')) {
        $values[$bundle_key] = $content['bundle'];
      }

      $entity = $storage->create($values);
    }

    switch ($content['entity_type']) {
      case 'node':
        if (isset($content['base_fields']['author']) && ($account = user_load_by_mail($content['base_fields']['author']))) {
          $entity->setOwner($account);
        }
        break;

      case 'taxonomy_term':
        if ($content['base_fields']['parent']) {
          $entity->set('parent', $this->doImport($content['base_fields']['parent']));
        }
        break;
    }

    // Import values from base fields.
    $this->importBaseValues($entity, $content['base_fields']);

    // Alter importing entity by using hook_content_import_entity_alter().
    // Support of importing a new entity type can be provided in the hook.
    $this->moduleHandler->alter('content_import_entity', $content, $entity);

    // Import values from custom fields.
    $this->importCustomValues($entity, $content['custom_fields']);

    $entity->save();

    // Sync translations of the entity.
    if (isset($content['translations']) && $entity instanceof TranslatableInterface) {
      foreach ($content['translations'] as $langcode => $translation_content) {
        $translated_entity = !$entity->hasTranslation($langcode) ? $entity->addTranslation($langcode) : $entity->getTranslation($langcode);

        $this->importBaseValues($translated_entity, $translation_content['base_fields']);
        $this->importCustomValues($translated_entity, $translation_content['custom_fields']);

        $translated_entity->save();
      }
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function importCustomValues(FieldableEntityInterface $entity, array $fields) {
    foreach ($fields as $field_name => $field_value) {
      $this->setFieldValue($entity, $field_name, $field_value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importBaseValues(FieldableEntityInterface $entity, array $fields) {
    $values = $this->mapBaseFieldsValues($entity->getEntityTypeId(), $fields);

    foreach ($values as $field_name => $value) {
      $entity->set($field_name, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldValue(FieldableEntityInterface $entity, $field_name, $field_value) {
    if (!$entity->hasField($field_name)) {
      return;
    }

    // Clear value.
    if (is_null($field_value)) {
      $entity->set($field_name, $field_value);
      return;
    }

    $field_definition = $entity->getFieldDefinition($field_name);

    switch ($field_definition->getType()) {
      case 'boolean':
      case 'datetime':
      case 'email':
      case 'link':
      case 'timestamp':
      case 'decimal':
      case 'float':
      case 'integer':
      case 'list_float':
      case 'list_integer':
      case 'list_string':
      case 'text':
      case 'text_long':
      case 'text_with_summary':
      case 'string':
      case 'string_long':
        $entity->set($field_name, $field_value);
        break;

      case 'entity_reference':
      case 'entity_reference_revisions':
        $values = [];
        foreach ($field_value as $child_entity) {
          // Import content entity relation.
          if (isset($child_entity['uuid']) && isset($child_entity['entity_type'])) {
            $values[] = $this->doImport($child_entity);
            continue;
          }

          // Import config relation just by setting target id.
          if ($child_entity['type'] === 'config') {
            $values[] = [
              'target_id' => $child_entity['value'],
            ];
          }
        }

        $entity->set($field_name, $values);
        break;

      case 'webform':
        $webform_storage = $this->entityTypeManager->getStorage('webform');

        if (isset($field_value['target_id'])) {
          if ($webform = $webform_storage->load($field_value['target_id'])) {
            $entity->set($field_name, $webform);
          }
        }
        break;

      case 'file':
      case 'image':
        $file_storage = $this->entityTypeManager->getStorage('file');
        $values = [];

        foreach ($field_value as $file_item) {
          $files = $file_storage->loadByProperties([
            'uri' => $file_item['uri'],
          ]);

          /** @var \Drupal\file\FileInterface $file */
          if (count($files)) {
            $file = reset($files);
          }
          else {
            if (!file_exists($file_item['url'])) {
              break;
            }

            $content = file_get_contents($file_item['url']);
            $directory = $this->fileSystem->dirname($file_item['uri']);
            $this->contentSyncHelper->prepareFilesDirectory($directory);

            if ($file = file_save_data($content, $file_item['uri'], FileSystemInterface::EXISTS_REPLACE)) {
              $file->setOwnerId(1);
              $file->setPermanent();
              $file->save();
            }
          }

          if (!$file) {
            break;
          }

          $file_value = [
            'target_id' => $file->id(),
          ];

          if (isset($file_item['alt'])) {
            $file_value['alt'] = $file_item['alt'];
          }

          if (isset($file_item['title'])) {
            $file_value['title'] = $file_item['title'];
          }

          if (isset($file_item['description'])) {
            $file_value['description'] = $file_item['description'];
          }

          $values[] = $file_value;
        }

        $entity->set($field_name, $values);
        break;

      case 'metatag':
        $entity->set($field_name, [['value' => serialize($field_value)]]);
        break;

      case 'layout_section':
        // Get unserialized version of each section.
        $sections = base64_decode($field_value);
        $values = array_map(function (string $section) {
          return unserialize($section);
        }, explode('|', $sections));

        $entity->set($field_name, $values);
        break;
    }

    // Alter setting a field value during the import by using
    // hook_content_import_field_value(). Support of importing a new field type
    // can be provided in the hook.
    $this->moduleHandler->alter('content_import_field_value', $entity, $field_name, $field_value);
  }

  /**
   * {@inheritdoc}
   */
  public function importFromFile(string $file_real_path): EntityInterface {
    if (!file_exists($file_real_path)) {
      throw new \Exception('The requested file does not exist.');
    }

    $file_content = file_get_contents($file_real_path);

    if (!$file_content) {
      throw new \Exception('The requested file could not be downloaded.');
    }

    $content = $this->contentSyncHelper->validateYamlFileContent($file_content);

    return $this->doImport($content);
  }

  /**
   * {@inheritdoc}
   */
  public function importFromZip(string $file_real_path): EntityInterface {
    // Extract zip files to the unique local directory.
    $zip = $this->contentSyncHelper->createZipInstance($file_real_path);
    $import_directory = $this->contentSyncHelper->createImportDirectory();
    $zip->extract($import_directory);

    $default_scheme = $this->contentSyncHelper->getDefaultFileScheme();
    $content_file_path = NULL;

    foreach ($zip->listContents() as $zip_file) {
      $original_file_path = "{$import_directory}/{$zip_file}";

      // Move the extracted assets to the proper destination.
      if (strpos($zip_file, 'assets') === 0) {
        // Use default schema instead of the assets destinations.
        $destination = str_replace('assets/', "{$default_scheme}://", $zip_file);
        $directory = $this->fileSystem->dirname($destination);
        $this->contentSyncHelper->prepareFilesDirectory($directory);
        $this->fileSystem->move($original_file_path, $destination, FileSystemInterface::EXISTS_REPLACE);
      }
      else {
        $content_file_path = $original_file_path;
      }
    }

    if (is_null($content_file_path)) {
      throw new \Exception('The content file in YAML format could not be found.');
    }

    $entity = $this->importFromFile($content_file_path);

    // Clean up extracted files/folder that are left after successful import.
    $this->fileSystem->deleteRecursive($import_directory);

    return $entity;
}

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues($entity_type_id, array $values) {
    $map_values = [];

    switch ($entity_type_id) {
      case 'node':
        return [
          'title' => $values['title'],
          'langcode' => $values['langcode'],
          'created' => $values['created'],
          'changed' => $values['changed'],
          'status' => $values['status'],
          'path' => [
            'alias' => $values['url'],
            'pathauto' => empty($values['url']),
          ],
        ];

      case 'user':
        return [
          'mail' => $values['mail'],
          'init' => $values['init'],
          'name' => $values['name'],
          'created' => $values['created'],
          'changed' => $values['changed'],
          'status' => $values['status'],
          'timezone' => $values['timezone'],
        ];

      case 'block_content':
        return [
          'langcode' => $values['langcode'],
          'info' => $values['info'],
          'reusable' => $values['reusable'],
        ];

      case 'media':
        return [
          'langcode' => $values['langcode'],
          'name' => $values['name'],
          'status' => $values['status'],
          'created' => $values['created'],
          'changed' => $values['changed'],
        ];

      case 'taxonomy_term':
        return [
          'name' => $values['name'],
          'weight' => $values['weight'],
          'langcode' => $values['langcode'],
          'description' => $values['description'],
        ];

      case 'paragraph':
        return [
          'langcode' => $values['langcode'],
          'created' => $values['created'],
          'status' => $values['status'],
        ];
    }

    return $map_values;
  }

}
