<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\file\FileInterface;
use Drupal\single_content_sync\ContentSyncHelperInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of file / media / image fields processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "file_asset",
 *   deriver = "Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor\FileAssetDeriver",
 * )
 */
class FileAsset extends SingleContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module private temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $privateTempStore;

  /**
   * The content sync helper service.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructs new FileAsset plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStore $private_temp_store
   *   The module private temporary storage.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    PrivateTempStore $private_temp_store,
    ContentSyncHelperInterface $content_sync_helper,
    FileSystemInterface $file_system
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStore = $private_temp_store;
    $this->contentSyncHelper = $content_sync_helper;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('single_content_sync.store'),
      $container->get('single_content_sync.helper'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
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

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    $file_storage = $this->entityTypeManager->getStorage('file');
    $values = [];

    foreach ($value as $file_item) {
      $files = $file_storage->loadByProperties([
        'uri' => $file_item['uri'],
      ]);

      /** @var \Drupal\file\FileInterface $file */
      if (count($files)) {
        $file = reset($files);
      }
      else {
        // Try to get and save a file by absolute url if file could not
        // be found after assets import.
        if (!file_exists($file_item['uri'])) {
          $data = file_get_contents($file_item['url']);

          if (!$data) {
            continue;
          }

          // Save external file to the proper destination.
          $directory = $this->fileSystem->dirname($file_item['uri']);
          $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
          $this->fileSystem->saveData($data, $file_item['uri']);
        }

        $file = $file_storage->create([
          'uid' => 1,
          'status' => FileInterface::STATUS_PERMANENT,
          'uri' => $file_item['uri'],
        ]);
        $file->save();
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

    $entity->set($fieldName, $values);
  }

}
