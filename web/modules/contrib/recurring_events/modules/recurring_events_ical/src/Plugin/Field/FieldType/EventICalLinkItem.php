<?php

namespace Drupal\recurring_events_ical\Plugin\Field\FieldType;

use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Plugin implementation of the 'event_ical_link' field type.
 *
 * @FieldType(
 *   id = "event_ical_link",
 *   label = @Translation("Event iCalendar Link"),
 *   description = @Translation("A link to an event's iCalendar download."),
 *   default_widget = "link_default",
 *   default_formatter = "event_ical_link",
 *   constraints = {
 *     "LinkType" = {},
 *     "LinkAccess" = {},
 *     "LinkExternalProtocols" = {},
 *     "LinkNotExistingInternal" = {},
 *   }
 * )
 */
class EventICalLinkItem extends LinkItem {
  // No implementation; only exists to define the default formatter.
}
