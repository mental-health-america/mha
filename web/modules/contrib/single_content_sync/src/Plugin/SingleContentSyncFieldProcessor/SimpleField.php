<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;

/**
 * Plugin implementation of the simple field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "simple_field",
 *   deriver = "Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor\SimpleFieldDeriver",
 * )
 */
class SimpleField extends SingleContentSyncFieldProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    return $field->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    $entity->set($fieldName, $value);
  }

}
