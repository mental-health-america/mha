<?php

namespace Drupal\Tests\recurring_events\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\KernelTests\KernelTestBase;
use Drupal\recurring_events\Plugin\Field\FieldType\YearlyRecurringDate;

/**
 * @coversDefaultClass \Drupal\recurring_events\Plugin\Field\FieldType\YearlyRecurringDate
 * @group recurring_events
 * @requires module field_inheritance
 */
class YearlyRecurringDateTest extends KernelTestBase {

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  protected static $modules = [
    'datetime',
    'datetime_range',
    'field_inheritance',
    'options',
    'recurring_events',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('eventseries');
    $this->installEntitySchema('eventinstance');
    $this->installConfig([
      'field_inheritance',
      'recurring_events',
      'datetime',
      'system',
    ]);
  }

  /**
   * Tests YearlyRecurringDate::findYearsBetweenDates().
   */
  public function testFindYearsBetweenDates() {
    $startDate = new DrupalDateTime('2020-01-01 00:00:00');
    $endDate = new DrupalDateTime('2025-01-01 00:00:00');
    $interval = 2;

    $expectedYears = [2020, 2022, 2024];
    $actualYears = YearlyRecurringDate::findYearsBetweenDates($startDate, $endDate, $interval);
    $this->assertEquals($expectedYears, $actualYears);
  }

}
