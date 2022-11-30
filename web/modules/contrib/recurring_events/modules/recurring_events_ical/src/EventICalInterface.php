<?php

namespace Drupal\recurring_events_ical;

use Drupal\recurring_events\EventInterface;

/**
 * Provides an interface for the event iCal service.
 */
interface EventICalInterface {

  /**
   * Renders an event in iCalendar format.
   *
   * @param \Drupal\recurring_events\EventInterface $event
   *   The event.
   *
   * @return string
   *   The event data in iCalendar format.
   */
  public function render(EventInterface $event): string;

}
