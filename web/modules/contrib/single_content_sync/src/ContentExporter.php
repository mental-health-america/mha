<?php

namespace Drupal\single_content_sync;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\field\FieldConfigInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

class ContentExporter implements ContentExporterInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Whether to extract translations.
   *
   * @var bool
   */
  protected $extractTranslationsMode;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The private temp store of the module.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $privateTempStore;

  /**
   * Local cache variable to store the reference info of entities.
   *
   * @var array
   */
  private $entityReferenceCache = [];

  /**
   * Local cache variable to store the exported output of entities.
   *
   * @var array
   */
  private $entityOutputCache = [];

  /**
   * ContentExporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\TempStore\PrivateTempStore $store
   *   The private temp store of the module.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, MessengerInterface $messenger, Connection $database, PrivateTempStore $store) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
    $this->database = $database;
    $this->privateTempStore = $store;
  }

  /**
   * Generates a cache key based on the entity's entity type id and uuid.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object for which to generate a cache key.
   *
   * @return string
   *   A string representing the entity's cache key.
   */
  protected function generateCacheKey(FieldableEntityInterface $entity): string {
    return implode('-', [$entity->getEntityTypeId(), $entity->uuid()]);
  }

  /**
   * Verifies whether a given entity is present in the entityOutputCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be verified in the cache.
   *
   * @return bool
   *   If the entity is present in the entityOutputCache will return TRUE,
   *   else will return FALSE.
   */
  protected function isOutputCached(FieldableEntityInterface $entity): bool {
    return array_key_exists($this->generateCacheKey($entity), $this->entityOutputCache);
  }

  /**
   * Verifies whether a given entity is present in the entityReferenceCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be verified in the cache.
   *
   * @return bool
   *   If the entity is present in the entityReferenceCache will return TRUE,
   *   else will return FALSE.
   */
  protected function isReferenceCached(FieldableEntityInterface $entity): bool {
    return array_key_exists($this->generateCacheKey($entity), $this->entityReferenceCache);
  }

  /**
   * Adds a given entity to the entityOutputCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be added to the entityOuputCache.
   * @param array $output
   *   The exported content output.
   */
  protected function addEntityToOutputCache(FieldableEntityInterface $entity, array $output): void {
    $id = $this->generateCacheKey($entity);
    $this->entityOutputCache[$id] = $output;
  }

  /**
   * Adds a given entity to the entityReferenceCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be added to the entityReferenceCache.
   */
  protected function addEntityToReferenceCache(FieldableEntityInterface $entity): void {
    $id = $this->generateCacheKey($entity);
    $this->entityReferenceCache[$id] = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function doExportToArray(FieldableEntityInterface $entity): array {
    // Add the entity to the entityReferenceCache array.
    $this->addEntityToReferenceCache($entity);

    // If the output was already cached, return the cached output.
    // Continue the method if not.
    if ($this->isOutputCached($entity)) {
      return $this->entityOutputCache[$this->generateCacheKey($entity)];
    }

    $output = [
      'uuid' => $entity->uuid(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'base_fields' => $this->exportBaseValues($entity),
      'custom_fields' => $this->exportCustomValues($entity),
    ];

    // Alter value by using hook_content_export_entity_alter().
    $this->moduleHandler->alter('content_export_entity', $output['base_fields'], $entity);

    // Display a message when we don't support base fields export for specific
    // entity type.
    if (!$output['base_fields']) {
      $this->messenger->addWarning($this->t('Base fields of "@entity_type" is not exportable out-of-the-box. Check README for a workaround.', [
        '@entity_type' => $output['entity_type'],
      ]));
    }

    // Extract translations.
    if ($this->extractTranslationsMode && $entity->isTranslatable()) {
      $translations = $entity->getTranslationLanguages();

      // Exclude the active language from the translations.
      unset($translations[$entity->language()->getId()]);

      if (count($translations)) {
        foreach ($translations as $language) {
          $translated_entity = $entity->getTranslation($language->getId());

          $output['translations'][$language->getId()]['base_fields'] = $this->exportBaseValues($translated_entity);
          $output['translations'][$language->getId()]['custom_fields'] = $this->exportCustomValues($translated_entity, TRUE);
        }
      }
    }

    // Add the output to the entityOutputCache array.
    $this->addEntityToOutputCache($entity, $output);

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function doExportToYml(FieldableEntityInterface $entity, $extract_translations = FALSE): string {
    // Remember the extract translation option to use it later.
    $this->extractTranslationsMode = (bool) $extract_translations;

    // Export content to array first.
    $output = $this->doExportToArray($entity);

    return Yaml::encode($output);
  }

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    $entity_type = $entity->getEntityTypeId();

    switch ($entity_type) {
      case 'node':
        if ($entity instanceof NodeInterface) {
          $owner = $entity->getOwner();

          return [
            'title' => $entity->getTitle(),
            'status' => $entity->isPublished(),
            'langcode' => $entity->language()->getId(),
            'created' => $entity->getCreatedTime(),
            'author' => $owner ? $owner->getEmail() : NULL,
            'url' => $entity->hasField('path') ? $entity->get('path')->alias : NULL,
          ];
        }
        break;

      case 'block_content':
        if ($entity instanceof BlockContentInterface) {
          return [
            'info' => $entity->label(),
            'reusable' => $entity->isReusable(),
            'langcode' => $entity->language()->getId(),
          ];
        }
        break;

      case 'media':
        if ($entity instanceof MediaInterface) {
          return [
            'name' => $entity->getName(),
            'created' => $entity->getCreatedTime(),
            'status' => $entity->isPublished(),
            'langcode' => $entity->language()->getId(),
          ];
        }
        break;

      case 'user':
        if ($entity instanceof UserInterface) {
          return [
            'mail' => $entity->getEmail(),
            'init' => $entity->getInitialEmail(),
            'name' => $entity->getAccountName(),
            'created' => $entity->getCreatedTime(),
            'status' => $entity->isActive(),
            'timezone' => $entity->getTimeZone(),
          ];
        }
        break;

      case 'taxonomy_term':
        if ($entity instanceof TermInterface) {
          return [
            'name' => $entity->getName(),
            'weight' => $entity->getWeight(),
            'langcode' => $entity->language()->getId(),
            'description' => $entity->getDescription(),
            'parent' => $entity->get('parent')->target_id
            ? $this->doExportToArray($entity->get('parent')->entity)
            : 0,
          ];
        }
        break;

      case 'paragraph':
        return [
          'status' => $entity->isPublished(),
          'langcode' => $entity->language()->getId(),
          'created' => $entity->getCreatedTime(),
        ];
    }

    // No base fields found for the entity.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function exportCustomValues(FieldableEntityInterface $entity, bool $check_translated_fields_only = FALSE): array {
    $fields = $check_translated_fields_only ? $entity->getTranslatableFields() : $entity->getFields();
    $values = [];

    foreach ($fields as $field) {
      if ($field->getFieldDefinition() instanceof FieldConfigInterface) {
        $values[$field->getName()] = !$field->isEmpty() ? $this->getFieldValue($field) : NULL;
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValue(FieldItemListInterface $field) {
    $value = NULL;
    $field_definition = $field->getFieldDefinition();
    $field_type = $field_definition->getType();
    $field_type_not_supported = FALSE;

    switch ($field_type) {
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
        $value = $field->getValue();
        break;

      case 'entity_reference':
        $value = [];
        $ids = array_column($field->getValue(), 'target_id');
        $storage = $this->entityTypeManager->getStorage($field->getSetting('target_type'));
        $entities = $storage->loadMultiple($ids);

        foreach ($entities as $child_entity) {
          if ($child_entity instanceof FieldableEntityInterface) {
            if (!$this->isReferenceCached($child_entity)) {
              // Export content entity relation.
              $value[] = $this->doExportToArray($child_entity);
            }
            else {
              $value[] = [
                'uuid' => $child_entity->uuid(),
                'entity_type' => $child_entity->getEntityTypeId(),
                'base_fields' => $this->exportBaseValues($child_entity),
                'bundle' => $child_entity->bundle(),
              ];
            }
          }
          // Support basic export of config entity relation.
          elseif ($child_entity instanceof ConfigEntityInterface) {
            $value[] = [
              'type' => 'config',
              'dependency_name' => $child_entity->getConfigDependencyName(),
              'value' => $child_entity->id(),
            ];
          }
        }
        break;

      case 'webform':
        $value = [
          'target_id' => $field->target_id,
        ];
        break;

      case 'entity_reference_revisions':
        $value = [];
        $ids = array_column($field->getValue(), 'target_id');
        $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
        $paragraphs = $paragraph_storage->loadMultiple($ids);

        foreach ($paragraphs as $paragraph) {
          $value[] = $this->doExportToArray($paragraph);
        }
        break;

      case 'file':
      case 'image':
        $assets = $this->privateTempStore->get('export.assets') ?? [];

        $value = [];
        $file_storage = $this->entityTypeManager->getStorage('file');

        foreach ($field->getValue() as $item) {
          $file = $file_storage->load($item['target_id']);

          $file_item = [
            'uri' => $file->getFileUri(),
            'url' => $file->createFileUrl(FALSE),
          ];

          $assets[] = $file_item['uri'];

          if (isset($item['alt'])) {
            $file_item['alt'] = $item['alt'];
          }

          if (isset($item['title'])) {
            $file_item['title'] = $item['title'];
          }

          if (isset($item['description'])) {
            $file_item['description'] = $item['description'];
          }

          $value[] = $file_item;
        }

        $assets = array_unique($assets);
        $assets = array_values($assets);

        // Let's store all exported assets in the private storage.
        // This will be used during exporting all assets to the zip later on.
        $this->privateTempStore->set('export.assets', $assets);
        break;

      case 'metatag':
        $field_value = $field->getValue();
        $value = !empty($field_value[0]['value']) ? unserialize($field_value[0]['value']) : [];
        break;

      case 'layout_section':
        $sections = $this->database->select('node__layout_builder__layout', 'n')
          ->fields('n', ['layout_builder__layout_section'])
          ->condition('n.entity_id', $field->getEntity()->id())
          ->execute()
          ->fetchCol();

        $value = base64_encode(implode('|', $sections));
        break;

      default:
        $field_type_not_supported = TRUE;
        break;
    }

    // Alter value by using hook_content_export_field_value_alter().
    $this->moduleHandler->alter('content_export_field_value', $value, $field);

    // Display a message about non-supported field types.
    if ($field_type_not_supported && is_null($value)) {
      $this->messenger->addWarning($this->t('The value of %field_label is empty because field type "@field_type" is not exportable out-of-the-box. Check README for a workaround.', [
        '%field_label' => $field_definition->getLabel(),
        '@field_type' => $field_type,
      ]));
    }

    return $value;
  }

}
