<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

interface ContentExporterInterface {

  /**
   * Export node as array.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to export.
   *
   * @return array
   *   The exported content as array.
   */
  public function doExportToArray(FieldableEntityInterface $entity);

  /**
   * Export node to YAML format.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to export.
   *
   * @return string
   *   The exported content in YML format.
   */
  public function doExportToYml(FieldableEntityInterface $entity);

  /**
   * Get field value in the proper format for further importing.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return array|string|bool
   *   The formatted field value.
   */
  public function getFieldValue(FieldItemListInterface $field);

}
