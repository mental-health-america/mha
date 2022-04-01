<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\FieldableEntityInterface;

interface ContentImporterInterface {

  /**
   * Import content.
   *
   * @param array $content
   *   Content to import.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Imported entity.
   */
  public function doImport(array $content);

  /**
   * Set field value.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to be imported.
   * @param string $field_name
   *   Field name.
   * @param mixed $field_value
   *   Field value.
   */
  public function setFieldValue(FieldableEntityInterface $entity, $field_name, $field_value);

  /**
   * Import content from the file.
   *
   * @param string $file_path
   *   The absolute path to the file.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Imported entity.
   */
  public function importFromFile($file_path);

}
