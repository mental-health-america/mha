<?php

namespace Drupal\Tests\recurring_events_ical\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * @coversDefaultClass \Drupal\recurring_events_ical\EventICal
 * @group recurring_events_ical
 */
class EventICalTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'datetime_range',
    'field',
    'field_inheritance',
    'options',
    'recurring_events',
    'recurring_events_ical',
    'system',
    'text',
    'token',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'core.entity_view_display.eventseries.default.default',
    'core.entity_view_display.eventseries.default.list',
    'core.entity_view_display.eventinstance.default.default',
    'core.entity_view_display.eventinstance.default.list',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\recurring_events_ical\EventICalInterface
   */
  protected $eventICal;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('field_inheritance');
    $this->installEntitySchema('eventseries_type');
    $this->installEntitySchema('eventinstance_type');
    $this->installEntitySchema('eventseries');
    $this->installEntitySchema('eventinstance');
    $this->installEntitySchema('event_ical_mapping');
    $this->installEntitySchema('user');
    $this->installConfig([
      'field_inheritance',
      'recurring_events',
      'recurring_events_ical',
      'datetime',
      'system',
      'user',
    ]);

    $this->eventICal = $this->container->get('recurring_events_ical.event_ical');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests EventICal::render() for basic values.
   */
  public function testRenderBasic() {
    $event = $this->createEventSeries(
      'Test event with a title that is longer than 75 characters. Seriously, it just keeps going and going.',
      [
        [
          'start' => '2022-01-01 00:00:00',
          'end' => '2022-01-01 00:30:00',
        ],
      ]
    );

    $iCal = explode("\r\n", $this->eventICal->render($event));
    $this->assertPreamble($iCal);
    $this->assertSame('DTSTART:20220101T000000Z', $iCal[6]);
    $this->assertSame('DTEND:20220101T003000Z', $iCal[7]);
    // cspell:ignore Seriousl
    $this->assertSame('SUMMARY:Test event with a title that is longer than 75 characters. Seriousl', $iCal[8]);
    $this->assertSame(' y, it just keeps going and going.', $iCal[9]);
    $this->assertSame('END:VEVENT', $iCal[10]);
    $this->assertSame('END:VCALENDAR', $iCal[11]);
  }

  /**
   * Tests EventICal::render() for mapped token values.
   */
  public function testRenderMapped() {
    $event = $this->createEventSeries(
      'Mapped event series',
      [
        [
          'start' => '2022-01-01 00:00:00',
          'end' => '2022-01-01 00:30:00',
        ],
      ],
      "<h2>Agenda</h2>\n\n<ul>\n\t<li>Talk about a lot of boring things</li>\n<li>Talk about more boring things</li>\n<li>Pizza</li>\n</ul>"
    );

    $mapping = $this->entityTypeManager->getStorage('event_ical_mapping')->create([
      'id' => 'default',
      'label' => 'Default',
      'properties' => [
        'summary' => '[eventinstance:title]',
        'contact' => 'That Guy',
        'description' => '[eventinstance:description]',
        'geo' => '12.34;56.78',
        'location' => 'Conference Room',
        'priority' => '1',
        'url' => '[eventinstance:url]',
      ],
    ]);
    $mapping->save();

    $iCal = explode("\r\n", $this->eventICal->render($event));
    $this->assertPreamble($iCal);
    $this->assertSame('DTSTART:20220101T000000Z', $iCal[6]);
    $this->assertSame('DTEND:20220101T003000Z', $iCal[7]);
    $this->assertSame('SUMMARY:Mapped event series', $iCal[8]);
    $this->assertSame('CONTACT:That Guy', $iCal[9]);
    $this->assertSame('DESCRIPTION:Agenda\n\n\n' . "\t" . 'Talk about a lot of boring things\nTalk about more', $iCal[10]);
    $this->assertSame('  boring things\nPizza', $iCal[11]);
    $this->assertSame('GEO:12.34;56.78', $iCal[12]);
    $this->assertSame('LOCATION:Conference Room', $iCal[13]);
    $this->assertSame('PRIORITY:1', $iCal[14]);
    $this->assertMatchesRegularExpression('~URL:(http|https)://(.+)/events/([0-9]+)~', $iCal[15]);
    $this->assertSame('END:VEVENT', $iCal[16]);
    $this->assertSame('END:VCALENDAR', $iCal[17]);
  }

  /**
   * Creates an event series for use in a test.
   *
   * @param string $title
   *   The series title.
   * @param array $dates
   *   An array of event start/end dates in the format: [
   *     [
   *       'start' => 'YYYY-MM-DD HH:MM:SS',
   *       'end' => 'YYYY-MM-DD HH:MM:SS',
   *     ],
   *     ...
   *   ].
   * @param string $body
   *   (optional) The event's body text.
   *
   * @return \Drupal\recurring_events\Entity\EventSeries
   *   The event series.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createEventSeries(string $title, array $dates, string $body = ''): EventSeries {
    $customDates = [];
    foreach ($dates as $date) {
      $start = new DrupalDateTime($date['start']);
      $end = new DrupalDateTime($date['end']);
      $customDates[] = [
        'value' => $start->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $end->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ];
    }

    /** @var \Drupal\recurring_events\Entity\EventSeries $event */
    $event = $this->entityTypeManager->getStorage('eventseries')->create([
      'title' => $title,
      'uid' => 1,
      'type' => 'default',
      'recur_type' => 'custom',
      'custom_date' => $customDates,
      'body' => $body,
    ]);
    $event->save();

    return $event;
  }

  /**
   * Asserts that the first six lines of iCal data are correct.
   *
   * @param array $iCal
   *   The iCal rendering exploded as an array.
   */
  protected function assertPreamble(array $iCal) {
    $this->assertSame('BEGIN:VCALENDAR', $iCal[0]);
    $this->assertSame('VERSION:2.0', $iCal[1]);
    $this->assertSame('PRODID:-//Drupal//recurring_events_ical//2.0//EN', $iCal[2]);
    $this->assertSame('BEGIN:VEVENT', $iCal[3]);
    $this->assertMatchesRegularExpression('/UID:([a-z0-9\-]+)@(.+)/', $iCal[4]);
    $this->assertMatchesRegularExpression('/DTSTAMP:([0-9]{8})T([0-9]{6})Z/', $iCal[5]);
  }

}
