<?php

namespace Drupal\recurring_events_ical\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\recurring_events\EventInterface;
use Drupal\recurring_events_ical\EventICalInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route controller for exporting event data in iCalendar format.
 */
class EventExportController extends ControllerBase {

  /**
   * The event iCal service.
   *
   * @var \Drupal\recurring_events_ical\EventICalInterface
   */
  protected $eventICal;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\recurring_events_ical\EventICalInterface $eventICal */
    $eventICal = $container->get('recurring_events_ical.event_ical');
    return new static(
      $eventICal
    );
  }

  /**
   * Constructs a new EventExportController instance.
   *
   * @param \Drupal\recurring_events_ical\EventICalInterface $eventICal
   *   The event iCal service.
   */
  public function __construct(EventICalInterface $eventICal) {
    $this->eventICal = $eventICal;
  }

  /**
   * Returns an iCalendar response for an event series.
   *
   * @param \Drupal\recurring_events\EventInterface $eventseries
   *   The event series.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An iCalendar file download.
   */
  public function series(EventInterface $eventseries): Response {
    return $this->response($eventseries);
  }

  /**
   * Returns an iCalendar response for an event instance.
   *
   * @param \Drupal\recurring_events\EventInterface $eventinstance
   *   The event instance.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An iCalendar file download.
   */
  public function instance(EventInterface $eventinstance): Response {
    return $this->response($eventinstance);
  }

  /**
   * Returns an event's iCalendar data as an HTTP response.
   *
   * @param \Drupal\recurring_events\EventInterface $event
   *   An event.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An iCalendar file download.
   */
  protected function response(EventInterface $event): Response {
    $headers = [
      'Content-Type' => 'text/calendar',
      'Content-Disposition' => 'attachment; filename="event.ics"',
    ];
    return new Response($this->eventICal->render($event), 200, $headers);
  }

}
