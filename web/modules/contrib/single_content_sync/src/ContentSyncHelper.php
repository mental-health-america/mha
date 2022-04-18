<?php

namespace Drupal\single_content_sync;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Archiver\ArchiverInterface;
use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\file\FileInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class ContentSyncHelper implements ContentSyncHelperInterface {

  use StringTranslationTrait;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The archiver manager.
   *
   * @var \Drupal\Core\Archiver\ArchiverManager
   */
  protected $archiverManager;

  /**
   * The uuid service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * ContentSyncHelper constructor.
   *
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid generator.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Archiver\ArchiverManager $archiver_manager
   *   The archive manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(UuidInterface $uuid, FileSystemInterface $file_system, ArchiverManager $archiver_manager, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->uuid = $uuid;
    $this->fileSystem = $file_system;
    $this->archiverManager = $archiver_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFilesDirectory(string &$directory): void {
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }

  /**
   * {@inheritdoc}
   */
  public function saveFileContentTemporary(string $content, string $destination): FileInterface {
    $file = file_save_data($content, $destination, FileSystemInterface::EXISTS_REPLACE);
    $file->setTemporary();
    $file->save();

    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function createImportDirectory(): string {
    $default_scheme = $this->getDefaultFileScheme();
    $uuid = $this->uuid->generate();
    $import_directory = "{$default_scheme}://import/zip/{$uuid}";

    $this->prepareFilesDirectory($import_directory);

    return $import_directory;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFileScheme(): string {
    return $this->configFactory->get('system.file')->get('default_scheme');
  }

  /**
   * {@inheritdoc}
   */
  public function createZipInstance(string $file_real_path): ArchiverInterface {
    return $this->archiverManager->getInstance([
      'filepath' => $file_real_path,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function generateContentFileName(EntityInterface $entity): string {
    return implode('-', [
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->uuid(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateYamlFileContent(string $content): array {
    $content = Yaml::decode($content);

    if (!is_array($content)) {
      throw new \Exception($this->t('YAML is not valid.'));
    }

    if (!isset($content['uuid']) || !isset($content['entity_type']) || !isset($content['base_fields']) || !isset($content['custom_fields'])) {
      throw new \Exception($this->t('The content of the YAML file is not valid. Make sure there are uuid, entity_type, base_fields, and custom_fields properties.'));
    }

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileRealPathById(int $fid): string {
    $storage = $this->entityTypeManager->getStorage('file');

    /** @var \Drupal\file\FileInterface $file */
    $file = $storage->load($fid);

    if (!$file) {
      throw new \Exception($this->t('The requested file could not be found.'));
    }

    return $this->fileSystem->realpath($file->getFileUri());
  }

}
