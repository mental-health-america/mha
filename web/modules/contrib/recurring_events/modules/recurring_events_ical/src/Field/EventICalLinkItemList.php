<?php

namespace Drupal\recurring_events_ical\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * A computed field that generates a link to download an event's iCalendar data.
 */
class EventICalLinkItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    if (!isset($this->list[0])) {
      $entity = $this->getEntity();
      if (!$entity->isNew()) {
        $this->list[0] = $this->createItem(0, [
          'uri' => $entity->toUrl('ical')->toUriString(),
          'title' => '',
        ]);
      }
    }
  }

}
