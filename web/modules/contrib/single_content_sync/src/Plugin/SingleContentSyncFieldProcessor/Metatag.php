<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;

/**
 * Plugin implementation for metatag field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "metatag",
 *   label = @Translation("Metatag field processor"),
 *   field_type = "metatag",
 * )
 */
class Metatag extends SingleContentSyncFieldProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $field_value = $field->getValue();

    return !empty($field_value[0]['value'])
      ? unserialize($field_value[0]['value'], ['allowed_classes' => FALSE])
      : [];
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    $entity->set($fieldName, [['value' => serialize($value)]]);
  }

}
