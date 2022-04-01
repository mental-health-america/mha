<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;

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
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public function doImport(array $content) {
    $entity = NULL;

    switch ($content['entity_type']) {
      case 'node':
        $node_storage = $this->entityTypeManager->getStorage('node');
        $entity = $this->entityRepository->loadEntityByUuid('node', $content['uuid']);

        if (!$entity) {
          $entity = $node_storage->create([
            'uuid' => $content['uuid'],
            'type' => $content['bundle'],
            'title' => $content['base_fields']['title'],
            'langcode' => $content['base_fields']['langcode'],
            'created' => $content['base_fields']['created'],
            'changed' => $content['base_fields']['changed'],
            'status' => $content['base_fields']['status'],
            'path' => [
              'alias' => $content['base_fields']['url'],
              'pathauto' => empty($content['base_fields']['url']),
            ],
          ]);
        }
        else {
          $entity->set('title', $content['base_fields']['title']);
          $entity->set('langcode', $content['base_fields']['langcode']);
          $entity->set('status', $content['base_fields']['status']);
          $entity->set('path', [
            'alias' => $content['base_fields']['url'],
            'pathauto' => 0,
          ]);
        }

        if (isset($content['base_fields']['author']) && ($account = user_load_by_mail($content['base_fields']['author']))) {
          $entity->setOwner($account);
        }
        break;

      case 'user':
        $user_storage = $this->entityTypeManager->getStorage('user');
        $entity = $this->entityRepository->loadEntityByUuid('user', $content['uuid']);

        if (!$entity) {
          $entity = $user_storage->create([
            'uuid' => $content['uuid'],
            'mail' => $content['base_fields']['mail'],
            'init' => $content['base_fields']['init'],
            'name' => $content['base_fields']['name'],
            'created' => $content['base_fields']['created'],
            'changed' => $content['base_fields']['changed'],
            'status' => $content['base_fields']['status'],
            'timezone' => $content['base_fields']['timezone'],
          ]);
        }
        else {
          $entity->set('mail', $content['base_fields']['mail']);
          $entity->set('name', $content['base_fields']['name']);
          $entity->set('status', $content['base_fields']['status']);
          $entity->set('timezone', $content['base_fields']['timezone']);
        }
        break;

      case 'block_content':
        $block_content_storage = $this->entityTypeManager->getStorage('block_content');
        $entity = $this->entityRepository->loadEntityByUuid('block_content', $content['uuid']);

        if (!$entity) {
          $entity = $block_content_storage->create([
            'uuid' => $content['uuid'],
            'type' => $content['bundle'],
            'langcode' => $content['base_fields']['langcode'],
            'info' => $content['base_fields']['info'],
            'reusable' => $content['base_fields']['reusable'],
          ]);
        }
        else {
          $entity->set('info', $content['base_fields']['info']);
          $entity->set('reusable', $content['base_fields']['reusable']);
        }
        break;

      case 'media':
        $media_storage = $this->entityTypeManager->getStorage('media');
        $entity = $this->entityRepository->loadEntityByUuid('media', $content['uuid']);

        if (!$entity) {
          $entity = $media_storage->create([
            'uuid' => $content['uuid'],
            'bundle' => $content['bundle'],
            'langcode' => $content['base_fields']['langcode'],
            'name' => $content['base_fields']['name'],
            'status' => $content['base_fields']['status'],
            'created' => $content['base_fields']['created'],
            'changed' => $content['base_fields']['changed'],
          ]);
        }
        else {
          $entity->set('name', $content['base_fields']['name']);
          $entity->set('created', $content['base_fields']['created']);
          $entity->set('changed', $content['base_fields']['changed']);
          $entity->set('status', $content['base_fields']['status']);
        }
        break;

      case 'taxonomy_term':
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $entity = $this->entityRepository->loadEntityByUuid('taxonomy_term', $content['uuid']);

        if (!$entity) {
          $entity = $term_storage->create([
            'uuid' => $content['uuid'],
            'name' => $content['base_fields']['name'],
            'weight' => $content['base_fields']['weight'],
            'langcode' => $content['base_fields']['langcode'],
            'description' => $content['base_fields']['description'],
          ]);

          if ($content['base_fields']['parent']) {
            $entity->set('parent', $this->doImport($content['base_fields']['parent']));
          }
        }
        else {
          $entity->set('name', $content['base_fields']['name']);
          $entity->set('langcode', $content['base_fields']['langcode']);
          $entity->set('weight', $content['base_fields']['weight']);
          $entity->set('description', $content['base_fields']['description']);
        }
        break;

      case 'paragraph':
        $entity = $this->entityRepository->loadEntityByUuid('paragraph', $content['uuid']);
        $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

        if (!$entity) {
          $entity = $paragraph_storage->create([
            'uuid' => $content['uuid'],
            'type' => $content['bundle'],
            'langcode' => $content['base_fields']['langcode'],
            'created' => $content['base_fields']['created'],
            'status' => $content['base_fields']['status'],
          ]);
        }
        else {
          $entity->set('status', $content['base_fields']['status']);
          $entity->set('langcode', $content['base_fields']['langcode']);
        }
        break;
    }

    // Alter importing entity by using hook_content_import_entity_alter().
    // Support of importing a new entity type can be provided in the hook.
    $this->moduleHandler->alter('content_import_entity', $content, $entity);

    // Import content field values.
    foreach ($content['custom_fields'] as $field_name => $field_value) {
      $this->setFieldValue($entity, $field_name, $field_value);
    }

    $entity->save();

    return $entity;
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
            $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

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
    }

    // Alter setting a field value during the import by using
    // hook_content_import_field_value(). Support of importing a new field type
    // can be provided in the hook.
    $this->moduleHandler->alter('content_import_field_value', $entity, $field_name, $field_value);
  }

  /**
   * {@inheritdoc}
   */
  public function importFromFile($file_path) {
    if (!file_exists($file_path)) {
      throw new \Exception('The requested file does not exists.');
    }

    $file_content = file_get_contents($file_path);

    if (!$file_content) {
      throw new \Exception('The requested file cannot be downloaded.');
    }

    $content = Yaml::decode($file_content);

    if (!$content) {
      throw new \Exception('The requested file has invalid YAML content.');
    }

    return $this->doImport($content);
  }

}
