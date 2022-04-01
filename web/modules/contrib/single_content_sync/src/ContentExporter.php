<?php

namespace Drupal\single_content_sync;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\FieldConfigInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

class ContentExporter implements ContentExporterInterface {

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
   * ContentExporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function doExportToArray(FieldableEntityInterface $entity) {
    $output = [
      'uuid' => $entity->uuid(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'base_fields' => [],
      'custom_fields' => [],
    ];

    switch ($output['entity_type']) {
      case 'node':
        if ($entity instanceof NodeInterface) {
          $owner = $entity->getOwner();
          $output['base_fields'] = [
            'title' => $entity->getTitle(),
            'status' => $entity->isPublished(),
            'langcode' => $entity->language()->getId(),
            'created' => $entity->getCreatedTime(),
            'changed' => $entity->getChangedTime(),
            'author' => $owner ? $owner->getEmail() : NULL,
            'url' => $entity->get('path')->alias ?? NULL,
          ];
        }
        break;

      case 'block_content':
        if ($entity instanceof BlockContentInterface) {
          $output['base_fields'] = [
            'info' => $entity->label(),
            'reusable' => $entity->isReusable(),
            'langcode' => $entity->language()->getId(),
          ];
        }
        break;

      case 'media':
        if ($entity instanceof MediaInterface) {
          $output['base_fields'] = [
            'name' => $entity->getName(),
            'created' => $entity->getCreatedTime(),
            'status' => $entity->isPublished(),
            'changed' => $entity->getChangedTime(),
            'langcode' => $entity->language()->getId(),
          ];
        }
        break;

      case 'user':
        if ($entity instanceof UserInterface) {
          $output['base_fields'] = [
            'mail' => $entity->getEmail(),
            'init' => $entity->getInitialEmail(),
            'name' => $entity->getAccountName(),
            'created' => $entity->getCreatedTime(),
            'changed' => $entity->getChangedTime(),
            'status' => $entity->isActive(),
            'timezone' => $entity->getTimeZone(),
          ];
        }
        break;

      case 'taxonomy_term':
        if ($entity instanceof TermInterface) {
          $output['base_fields'] = [
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
        $output['base_fields'] = [
          'status' => $entity->isPublished(),
          'langcode' => $entity->language()->getId(),
          'created' => $entity->getCreatedTime(),
        ];
        break;
    }

    // Alter value by using hook_content_export_entity_alter().
    $this->moduleHandler->alter('content_export_entity', $output['base_fields'], $entity);

    foreach ($entity->getFields() as $field) {
      if ($field->getFieldDefinition() instanceof FieldConfigInterface) {
        $output['custom_fields'][$field->getName()] = !$field->isEmpty() ? $this->getFieldValue($field) : NULL;
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function doExportToYml(FieldableEntityInterface $entity) {
    $output = $this->doExportToArray($entity);

    return Yaml::encode($output);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValue(FieldItemListInterface $field) {
    $value = NULL;
    $field_definition = $field->getFieldDefinition();

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
        $value = $field->getValue();
        break;

      case 'entity_reference':
        $value = [];
        $ids = array_column($field->getValue(), 'target_id');
        $storage = $this->entityTypeManager->getStorage($field->getSetting('target_type'));
        $entities = $storage->loadMultiple($ids);

        foreach ($entities as $child_entity) {
          // Export content entity relation.
          if ($child_entity instanceof FieldableEntityInterface) {
            $value[] = $this->doExportToArray($child_entity);
          }
          // Support basic export og config entity relation.
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
        $value = [];
        $file_storage = $this->entityTypeManager->getStorage('file');

        foreach ($field->getValue() as $item) {
          $file = $file_storage->load($item['target_id']);

          $file_item = [
            'uri' => $file->getFileUri(),
            'url' => $file->createFileUrl(FALSE),
          ];

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
        break;
    }

    // Alter value by using hook_content_export_field_value_alter().
    $this->moduleHandler->alter('content_export_field_value', $value, $field);

    return $value;
  }

}
