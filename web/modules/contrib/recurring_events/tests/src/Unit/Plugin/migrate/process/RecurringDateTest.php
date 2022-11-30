<?php

namespace Drupal\Tests\recurring_events\Unit\Plugin\migrate\process;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\recurring_events\Plugin\migrate\process\RecurringDate;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\recurring_events\Plugin\migrate\process\RecurringDate
 * @group recurring_events
 */
class RecurringDateTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mocks for non-injected services used by DrupalDateTime.
    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('en');
    $languageManager = $this->prophesize(LanguageManagerInterface::class);
    $languageManager->getCurrentLanguage()->willReturn($language->reveal());
    $stringTranslation = $this->prophesize(TranslationInterface::class);
    $stringTranslation->translateString(Argument::type(TranslatableMarkup::class))->will(function ($args) {
      return $args[0]->getUntranslatedString();
    });

    // Mocked service container.
    $container = new ContainerBuilder();
    $container->set('language_manager', $languageManager->reveal());
    $container->set('string_translation', $stringTranslation->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Tests processing simple arrays of datetimes.
   *
   * @covers ::transform
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function testTransformSimpleValues() {
    $configuration = [
      'default_timezone' => 'America/New_York',
    ];
    $this->plugin = new RecurringDate($configuration, 'recurring_date', []);

    $source = [
      [
        '20220719T120000',
        '20220719T130000',
      ],
    ];
    $expected = [
      [
        'value' => '2022-07-19T16:00:00',
        'end_value' => '2022-07-19T17:00:00',
      ],
    ];
    $value = $this->plugin->transform($source, $this->migrateExecutable, $this->row, 'destination_property');
    $this->assertSame($expected, $value);
  }

  /**
   * Tests processing associative arrays with timezones and RRULEs.
   *
   * @covers ::transform
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function testTransformComplexValues() {
    $configuration = [
      'value_key' => 'start',
      'end_value_key' => 'end',
      'rrule_key' => 'rrule',
      'timezone_key' => 'timezone',
      'default_timezone' => 'America/New_York',
    ];
    $this->plugin = new RecurringDate($configuration, 'recurring_date', []);

    $source = [
      [
        'start' => '20220719T120000',
        'end' => '20220719T140000',
        'rrule' => 'FREQ=WEEKLY;UNTIL=20220802T140000',
        'timezone' => 'America/Detroit',
      ],
    ];
    $expected = [
      'value' => '2022-07-19T16:00:00',
      'end_value' => '2022-08-02T18:00:00',
      'time' => '04:00 pm',
      'end_time' => '06:00 pm',
      'duration' => 7200,
      'duration_or_end_time' => 'end_time',
      'days' => 'tuesday',
    ];
    $value = $this->plugin->transform($source, $this->migrateExecutable, $this->row, 'destination_property');
    $this->assertSame($expected, $value);
  }

}
