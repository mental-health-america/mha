<?php

namespace Drupal\group_recurring_events_series\Access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

use Drupal\recurring_events\EventInstanceAccessControlHandler;

/**
 * The GroupEventInstanceHandler class.
 */
class GroupEventInstanceHandler extends EventInstanceAccessControlHandler {

  /**
   * Determine access for the group.
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $result = parent::checkAccess($entity, $operation, $account);
    if (!$result->isAllowed()) {
      // Need GroupContentAccessControlHandler not
      // EventSeriesAccessControlHandler.
      $manager = \Drupal::service('plugin.manager.group_content_enabler');
      $type = 'group_recurring_events_series:' . $entity->getType();
      if ($manager->hasHandler($type, 'access')) {
        $handler = $manager->getAccessControlHandler($type);
        $result = $handler->entityAccess($entity->getEventSeries(), $operation, $account, TRUE);
      }
    }
    return $result;
  }

}
