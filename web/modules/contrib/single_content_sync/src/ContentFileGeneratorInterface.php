<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\file\FileInterface;

interface ContentFileGeneratorInterface {

  /**
   * Generate a YAML file with the exported content.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object.
   * @param bool $extract_translations
   *   Whether to extract translations.
   *
   * @return \Drupal\file\FileInterface
   *   The generate file represented as object.
   */
  public function generateYamlFile(FieldableEntityInterface $entity, bool $extract_translations = FALSE): FileInterface;

  /**
   * Generate a Zip file with the exported content and assets.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object.
   * @param bool $extract_translations
   *   Whether to extract translations.
   *
   * @return \Drupal\file\FileInterface
   *   The generate file represented as object.
   */
  public function generateZipFile(FieldableEntityInterface $entity, bool $extract_translations = FALSE): FileInterface;

}
