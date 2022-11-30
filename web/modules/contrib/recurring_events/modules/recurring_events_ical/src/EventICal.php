<?php

namespace Drupal\recurring_events_ical;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\recurring_events\Entity\EventSeries;
use Drupal\recurring_events\EventInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a service to get the iCalendar data for an event.
 */
class EventICal implements EventICalInterface {

  const VERSION = '2.0';
  const PRODID = '-//Drupal//recurring_events_ical//2.0//EN';
  const DATETIMEFORMAT = 'Ymd\THis\Z';
  const LINELENGTH = 75;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs an EventICalService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Utility\Token $token
   *   The tokens service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack, Token $token) {
    $this->entityTypeManager = $entityTypeManager;
    $this->request = $requestStack->getCurrentRequest();
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function render(EventInterface $event): string {
    /** @var \Drupal\recurring_events_ical\Entity\EventICalMapping|null $mapping */
    $mapping = $this->entityTypeManager->getStorage('event_ical_mapping')->load($event->bundle());

    /** @var \Drupal\recurring_events\Entity\EventInstance[] $instances */
    $instances = $event instanceof EventSeries
      ? $event->get('event_instances')->referencedEntities()
      : [$event];

    $output = [];
    $output[] = 'BEGIN:VCALENDAR';
    $output[] = 'VERSION:' . static::VERSION;
    $output[] = 'PRODID:' . static::PRODID;

    foreach ($instances as $instance) {
      $output[] = 'BEGIN:VEVENT';
      $output[] = $this->prepareValue('UID', $instance->uuid() . '@' . $this->request->getHost());
      $output[] = 'DTSTAMP:' . date(static::DATETIMEFORMAT, $instance->getChangedTime());
      $output[] = 'DTSTART:' . $instance->date->start_date->format(static::DATETIMEFORMAT);
      $output[] = 'DTEND:' . $instance->date->end_date->format(static::DATETIMEFORMAT);
      if ($mapping) {
        foreach ($mapping->getAllProperties() as $property => $value) {
          // Process any tokens, removing those that have no replacement.
          $value = $this->token->replace($value, ['eventinstance' => $instance], [
            'clear' => TRUE,
          ]);
          $value = $this->prepareValue($property, $value);
          if (!empty($value)) {
            $output[] = $value;
          }
        }
      }
      else {
        // The summary is required, so if there's no mapping in place, default
        // to the event label.
        $output[] = $this->prepareValue('SUMMARY', $instance->label());
      }
      $output[] = 'END:VEVENT';
    }

    $output[] = 'END:VCALENDAR';
    return implode("\r\n", $output);
  }

  /**
   * Prepares a property value on an event for output as iCalendar data.
   *
   * @param string $property
   *   The property name.
   * @param string $value
   *   The raw value.
   *
   * @return string
   *   The property and sanitized value, formatted as required by RFC 5545.
   */
  protected function prepareValue(string $property, string $value): string {
    // Sanitize twice to ensure that all tags and HTML codes are removed. For
    // example, if the value came in as "&lt;p&gt;Hello,&#039;World!&lt;/p&gt;",
    // the first pass would return "<p>Hello,&nbsp;World!</p>" and the second
    // pass would return "Hello, World!", which is what we need.
    $value = trim(PlainTextOutput::renderFromHtml(PlainTextOutput::renderFromHtml($value)));

    // If there's nothing left after processing, return an empty string so that
    // the property is omitted.
    if (empty($value)) {
      return '';
    }
    $value = strtoupper($property) . ':' . $value;

    // Change all existing line endings to literal \n.
    $value = str_replace(["\r\n", "\n\r", "\r", "\n"], '\n', $value);

    // RFC 5545 3.1 requires lines longer than 75 bytes to be wrapped with CRLF
    // followed by a single space.
    $wrapped = [];
    // Remember: strlen() counts bytes, not characters.
    while (strlen($value) > static::LINELENGTH) {
      // Grab a chunk up to line length, without splitting multibyte characters.
      $chunk = $this->cut($value, 0, static::LINELENGTH, 'UTF-8');
      $wrapped[] = $chunk;
      // The required space after the CRLF counts against the line length, so
      // add it to the front of the remaining text for the next loop.
      $value = ' ' . $this->cut($value, strlen($chunk), NULL, 'UTF-8');
    }
    // $value now contains whatever is left on the last line after wrapping.
    $wrapped[] = $value;

    return implode("\r\n", $wrapped);
  }

  /**
   * Wrapper for mb_strcut because Symfony's Mbstring polyfill doesn't have it.
   *
   * @param string $string
   *   The string being cut.
   * @param int $start
   *   The start position in bytes.
   * @param int|null $length
   *   The length of the cut in bytes. If NULL, runs to the end of the string.
   * @param string|null $encoding
   *   The character encoding.
   *
   * @return string
   *   The portion of $string specified by the start and length parameters.
   *
   * @see mb_strcut()
   */
  protected function cut(string $string, int $start, ?int $length = NULL, ?string $encoding = NULL): string {
    if (function_exists('mb_strcut')) {
      return mb_strcut($string, $start, $length, $encoding);
    }
    return substr($string, $start, $length);
  }

}
