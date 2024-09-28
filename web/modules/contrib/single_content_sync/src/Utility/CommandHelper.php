<?php

namespace Drupal\single_content_sync\Utility;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\ContentSyncHelperInterface;

/**
 * Provides functionality to be used by CLI tools.
 */
class CommandHelper implements CommandHelperInterface {

  /**
   * The content importer service.
   *
   * @var \Drupal\single_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $contentImporter;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The content sync helper service.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The app root.
   *
   * @var string
   */
  protected string $root;

  /**
   * Constructor of ContentSyncCommands.
   *
   * @param \Drupal\single_content_sync\ContentImporterInterface $content_importer
   *   The content importer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param string $root
   *   The app root.
   */
  public function __construct(
    ContentImporterInterface $content_importer,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ContentSyncHelperInterface $content_sync_helper,
    FileSystemInterface $file_system,
    string $root
  ) {
    $this->contentImporter = $content_importer;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->contentSyncHelper = $content_sync_helper;
    $this->fileSystem = $file_system;
    $this->root = $root;
  }

  /**
   * {@inheritDoc}
   */
  public function createMessageWithFlags(string $message, array $options = []): string {
    $include_translations = $options['translate'] ?? FALSE;
    $include_assets = $options['assets'] ?? FALSE;
    $all_allowed_content = $options['all-content'] ?? FALSE;
    $is_dry_run = $options['dry-run'] ?? FALSE;
    $entity_ids_to_export = $options['entities'] ?? NULL;

    $flags = $include_translations ? ' --translate' : '';
    $flags .= $include_assets ? ' --assets' : '';
    $flags .= $all_allowed_content ? ' --all-content' : '';
    $flags .= $is_dry_run ? ' --dry-run' : '';
    $flags .= $entity_ids_to_export ? " --entities=\"{$entity_ids_to_export}\"" : '';

    return "{$message}{$flags}\n\n";
  }

  /**
   * {@inheritDoc}
   */
  public function commandZipImport(string $file_path): void {
    $this->contentImporter->importFromZip($file_path);
    drush_backend_batch_process();
  }

  /**
   * {@inheritDoc}
   */
  public function getEntitiesToExport(string $entityType = 'node', string $bundle = '', bool $all_allowed_content = FALSE, string $entity_ids_to_export = NULL): array {
    if ($all_allowed_content) {
      $allowed_entity_types = $this->configFactory->get('single_content_sync.settings')->get('allowed_entity_types');
      $entities = array_reduce($this->entityTypeManager->getDefinitions(), function ($carry, $entity_type) use ($allowed_entity_types) {
        if (array_key_exists($entity_type->id(), $allowed_entity_types)) {
          return array_merge($carry, $this->entityTypeManager->getStorage($entity_type->id())->loadMultiple());
        }
        return $carry;
      }, []);
    }
    elseif ($entity_ids_to_export) {
      $entities = $this->getSelectedEntities($entityType, $entity_ids_to_export);
    }
    elseif (!empty($bundle)) {
      $bundle_names = [
        'node' => 'type',
        'taxonomy_term' => 'vid',
      ];
      $entities = $this->entityTypeManager->getStorage($entityType)->loadByProperties([$bundle_names[$entityType] => $bundle]);
    }
    else {
      $entities = $this->entityTypeManager->getStorage($entityType)->loadMultiple();
    }
    return $entities;
  }

  /**
   * {@inheritDoc}
   */
  public function getSelectedEntities(string $entity_type, string $ids_to_export): array {
    $entity_ids = explode(',', $ids_to_export);
    $entities = [];
    $invalid_ids = array_filter($entity_ids, function ($id) {
      return !intval($id);
    });

    if (!empty($invalid_ids)) {
      $err_out = implode(', ', $invalid_ids);
      throw new \Exception("The export couldn't be completed because the --entities contain invalid ids: {$err_out}");
    }

    foreach ($entity_ids as $id) {
      $entity = $this->entityTypeManager->getStorage($entity_type)
        ->load($id);
      if (!$entity) {
        throw new \Exception("The export couldn't be completed because the --entities contain invalid id: {$id}");
      }
      $entities[] = $entity;
    }

    return $entities;
  }

  /**
   * {@inheritDoc}
   */
  public function moveFile(FileInterface $file, string $output_dir, string $file_target): string {
    if (!$output_dir) {
      return $file_target;
    }

    $target_base_name = basename($file_target);
    $moved_file_path = "{$output_dir}/{$target_base_name}";
    $this->fileSystem->move($file->getFileUri(), $moved_file_path);

    return $moved_file_path;
  }

  /**
   * {@inheritDoc}
   */
  public function getRealDirectory(string $output_path): string {
    $grandparent_path = $this->root;
    if (!$output_path) {
      return $grandparent_path . '/scs-export';
    }

    if (substr($output_path, 0, strlen('./')) === './') {
      $output_path = substr($output_path, 2);
    }

    $relative_dir = rtrim($output_path, '/');
    $parent_count = substr_count($relative_dir, '../');
    $grandparent_path = !!$parent_count ? dirname($grandparent_path, $parent_count) : $grandparent_path;
    $trimmed_relative_dir = ltrim(str_replace('../', '', $relative_dir), '/');
    $output_dir = "{$grandparent_path}/{$trimmed_relative_dir}";

    return $output_dir;
  }

}
