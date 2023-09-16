<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for node base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "node",
 *   label = @Translation("Node base fields processor"),
 *   entity_type = "node",
 * )
 */
class Node extends SingleContentSyncBaseFieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The content exporter.
   *
   * @var \Drupal\single_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $exporter;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, ContentExporterInterface $exporter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->exporter = $exporter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('single_content_sync.exporter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    $owner = $entity->getOwner();

    $base_fields = [
      'title' => $entity->getTitle(),
      'status' => $entity->isPublished(),
      'langcode' => $entity->language()->getId(),
      'created' => $entity->getCreatedTime(),
      'author' => $owner ? $owner->getEmail() : NULL,
      'url' => $entity->hasField('path') ? $entity->get('path')->alias : NULL,
      'revision_log_message' => $entity->getRevisionLogMessage(),
      'revision_uid' => $entity->getRevisionUserId(),
    ];

    if ($this->moduleHandler->moduleExists('menu_ui')) {
      $menu_link = menu_ui_get_menu_link_defaults($entity);
      $storage = $this->entityTypeManager->getStorage('menu_link_content');

      // Export content menu link item if available.
      if (!empty($menu_link['entity_id']) && ($menu_link_entity = $storage->load($menu_link['entity_id']))) {
        assert($menu_link_entity instanceof MenuLinkContentInterface);

        // Avoid infinitive loop, export menu link only once.
        if (!$this->exporter->isReferenceCached($menu_link_entity)) {
          $base_fields['menu_link'] = $this->exporter->doExportToArray($menu_link_entity);
        }
      }
    }

    return $base_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values): array {
    $entity = [
      'title' => $values['title'],
      'langcode' => $values['langcode'],
      'created' => $values['created'],
      'status' => $values['status'],
    ];

    // We check if node url alias is filled in.
    if (isset($values['url'])) {
      $entity['path'] = [
        'alias' => $values['url'],
        'pathauto' => empty($values['url']),
      ];
    }

    return $entity;
  }

}
