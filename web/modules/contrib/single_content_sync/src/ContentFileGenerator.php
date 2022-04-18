<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Archiver\ArchiverInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\file\FileInterface;

class ContentFileGenerator implements ContentFileGeneratorInterface {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content sync helper.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected $contentSyncHelper;

  /**
   * The content exporter.
   *
   * @var \Drupal\single_content_sync\ContentExporterInterface
   */
  protected $contentExporter;

  /**
   * ContentFileGenerator constructor.
   *
   * @param \Drupal\Core\file\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   * @param \Drupal\single_content_sync\ContentExporterInterface $content_exporter
   *   The content exporter.
   */
  public function __construct(FileSystemInterface $file_system, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, ContentSyncHelperInterface $content_sync_helper, ContentExporterInterface $content_exporter) {
    $this->fileSystem = $file_system;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->contentSyncHelper = $content_sync_helper;
    $this->contentExporter = $content_exporter;
  }

  /**
   * {@inheritdoc}
   */
  public function generateYamlFile(FieldableEntityInterface $entity, bool $extract_translations = FALSE): FileInterface {
    $output = $this->contentExporter->doExportToYml($entity, $extract_translations);
    $default_scheme = $this->contentSyncHelper->getDefaultFileScheme();
    $directory = "{$default_scheme}://export";
    $file_name = $this->contentSyncHelper->generateContentFileName($entity);
    $this->contentSyncHelper->prepareFilesDirectory($directory);

    return $this->contentSyncHelper->saveFileContentTemporary($output, "{$directory}/{$file_name}.yml");
  }

  /**
   * {@inheritdoc}
   */
  public function generateZipFile(FieldableEntityInterface $entity, bool $extract_translations = FALSE): FileInterface {
    $export_file = $this->generateYamlFile($entity, $extract_translations);
    $default_scheme = $this->contentSyncHelper->getDefaultFileScheme();

    // Generate an empty zip file to be used for storing the exported content.
    $directory = "{$default_scheme}://export/zip";
    $this->contentSyncHelper->prepareFilesDirectory($directory);
    $zip_name = $this->contentSyncHelper->generateContentFileName($entity);
    $zip_file = $this->contentSyncHelper->saveFileContentTemporary('', "{$directory}/{$zip_name}.zip");

    $zip_file_path = $this->fileSystem->realpath($zip_file->getFileUri());
    $zip = $this->contentSyncHelper->createZipInstance($zip_file_path);

    // Add exported content to the zip file.
    $content_file_path = $this->fileSystem->realpath($export_file->getFileUri());
    $zip->getArchive()->addFile($content_file_path, $export_file->getFileName());

    // Add image and file assets to the zip file.
    foreach (['image', 'file'] as $field_type) {
      $this->addAssetsToZip($zip, $entity, $field_type);
    }

    return $zip_file;
  }

  /**
   * Add assets to zip file.
   *
   * @param \Drupal\Core\Archiver\ArchiverInterface $zip
   *   The zip file to which the assets will be added.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity from which the assets will be added to the zip file.
   * @param string $file_type
   *   Which type of assets are added to the zip file.
   */
  protected function addAssetsToZip(ArchiverInterface $zip, FieldableEntityInterface $entity, string $file_type): void {
    $fields = $this->entityFieldManager->getFieldMapByFieldType($file_type);
    $fields_entity_type = $fields[$entity->getEntityTypeId()];
    $file_fields = array_keys($fields_entity_type);

    // Go through each file field of the entity.
    foreach ($file_fields as $field_name) {
      if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
        foreach ($entity->get($field_name)->getValue() as $file_item) {
          /** @var \Drupal\file\FileInterface $file */
          $file = $this->entityTypeManager->getStorage('file')
            ->load($file_item['target_id']);

          // Add file to the zip.
          $file_uri = $file->getFileUri();
          $file_full_path = $this->fileSystem->realpath($file_uri);
          $file_relative_path = explode('://', $file_uri)[1];
          $zip->getArchive()->addFile($file_full_path, "assets/{$file_relative_path}");
        }
      }
    }
  }

}
