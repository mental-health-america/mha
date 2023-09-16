<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;

/**
 * Plugin implementation for paragraph base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "paragraph",
 *   label = @Translation("Paragraph base fields processor"),
 *   entity_type = "paragraph",
 * )
 */
class Paragraph extends SingleContentSyncBaseFieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    return [
      'status' => $entity->isPublished(),
      'langcode' => $entity->language()->getId(),
      'created' => $entity->getCreatedTime(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values): array {
    return [
      'langcode' => $values['langcode'],
      'created' => $values['created'],
      'status' => $values['status'],
    ];
  }

}
