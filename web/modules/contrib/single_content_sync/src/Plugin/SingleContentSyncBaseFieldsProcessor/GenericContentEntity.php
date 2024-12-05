<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;
use Drupal\user\EntityOwnerInterface;

/**
 * Plugin implementation for generic fieldable entity.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "generic",
 *   label = @Translation("Generic entity fields processor"),
 *   entity_type = ""
 * )
 */
class GenericContentEntity extends SingleContentSyncBaseFieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    $owner = $entity instanceof EntityOwnerInterface ? $entity->getOwner() : NULL;
    $ownerField = 'owner';
    $entityKeys = $entity->getEntityType()->getKeys();
    if ($owner && !empty($entityKeys['owner'])) {
      $ownerField = $entityKeys['owner'];
    }

    $base_fields = [
      $entityKeys['label'] => $entity->label(),
      'status' => ($entity instanceof EntityPublishedInterface) ? $entity->isPublished() : NULL,
      'langcode' => $entity->language()->getId(),
      'created' => method_exists($entity, 'getCreatedTime') ? $entity->getCreatedTime() : NULL,
      $ownerField => $owner ? $owner->id() : NULL,
      'url' => $entity->hasField('path') ? $entity->get('path')->alias : NULL,
      'revision_log_message' => method_exists($entity, 'getRevisionLogMessage')
        ? $entity->getRevisionLogMessage() : NULL,
      'revision_uid' => method_exists($entity, 'getRevisionUserId')
        ? $entity->getRevisionUserId() : NULL,
    ];

    return array_filter($base_fields);
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $fieldableEntity): array {
    $entity = $values;

    // We check if node url alias is filled in.
    if (isset($values['url'])) {
      $entity['path'] = [
        'alias' => $values['url'],
        'pathauto' => empty($values['url']),
      ];
      unset($entity['url']);
    }

    return array_filter($entity);
  }

}
