<?php

namespace Drupal\recurring_events\Plugin\Field\FieldType;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Plugin implementation of the 'yearly_recurring_date' field type.
 *
 * @FieldType (
 *   id = "yearly_recurring_date",
 *   label = @Translation("Yearly Event"),
 *   description = @Translation("Stores a yearly recurring date configuration"),
 *   default_widget = "yearly_recurring_date",
 *   default_formatter = "",
 *   no_ui = TRUE,
 * )
 */
class YearlyRecurringDate extends MonthlyRecurringDate {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['year_interval'] = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ];

    $schema['columns']['months'] = [
      'type' => 'varchar',
      'length' => 255,
      'not null' => TRUE,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $interval = $this->get('year_interval')->getValue();
    $months = $this->get('months')->getValue();
    return parent::isEmpty() && empty($interval) && empty($months);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['year_interval'] = DataDefinition::create('integer')
      ->setLabel(t('Year interval'))
      ->setDescription(t('Number of years between occurrences'));

    $properties['months'] = DataDefinition::create('string')
      ->setLabel(t('Months'))
      ->setDescription(t('The months in which the event occurs'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function convertEntityConfigToArray(EventSeries $event) {
    $config = [];
    $config['start_date'] = $event->getYearlyStartDate();
    $config['end_date'] = $event->getYearlyEndDate();
    $config['time'] = strtoupper($event->getYearlyStartTime());
    $config['end_time'] = strtoupper($event->getYearlyEndTime());
    $config['duration'] = $event->getYearlyDuration();
    $config['duration_or_end_time'] = $event->getYearlyDurationOrEndTime();
    $config['monthly_type'] = $event->getYearlyType();

    switch ($event->getYearlyType()) {
      case 'weekday':
        $config['day_occurrence'] = $event->getYearlyDayOccurrences();
        $config['days'] = $event->getYearlyDays();
        break;

      case 'monthday':
        $config['day_of_month'] = $event->getYearlyDayOfMonth();
        break;
    }

    $config['year_interval'] = $event->getYearlyInterval();
    $config['months'] = $event->getYearlyMonths();

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function convertFormConfigToArray(FormStateInterface $form_state) {
    $config = [];

    $userTimezone = new \DateTimeZone(date_default_timezone_get());
    $userInput = $form_state->getValues();

    $time = $userInput['yearly_recurring_date'][0]['time'];
    if (is_array($time)) {
      $temp = DrupalDateTime::createFromFormat('H:i:s', $time['time']);
      $time = $temp->format('h:i A');
    }
    $timeParts = static::convertTimeTo24hourFormat($time);
    $timestamp = implode(':', $timeParts);

    $userInput['yearly_recurring_date'][0]['value']->setTimezone($userTimezone);
    $startTimestamp = $userInput['yearly_recurring_date'][0]['value']->format('Y-m-d') . 'T' . $timestamp;
    $startDate = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $startTimestamp, $userTimezone);
    $startDate->setTime(0, 0, 0);

    $endTime = $userInput['yearly_recurring_date'][0]['end_time']['time'];
    if (is_array($endTime)) {
      $temp = DrupalDateTime::createFromFormat('H:i:s', $endTime['time']);
      $endTime = $temp->format('h:i A');
    }
    $endTimeParts = static::convertTimeTo24hourFormat($endTime);
    $endTimestamp = implode(':', $endTimeParts);

    $userInput['yearly_recurring_date'][0]['end_value']->setTimezone($userTimezone);
    $endTimestamp = $userInput['yearly_recurring_date'][0]['end_value']->format('Y-m-d') . 'T' . $endTimestamp;
    $endDate = DrupalDateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $endTimestamp, $userTimezone);
    $endDate->setTime(0, 0, 0);

    $config['start_date'] = $startDate;
    $config['end_date'] = $endDate;

    $config['time'] = strtoupper($time);
    $config['end_time'] = strtoupper($endTime);
    $config['duration'] = $userInput['yearly_recurring_date'][0]['duration'];
    $config['duration_or_end_time'] = $userInput['yearly_recurring_date'][0]['duration_or_end_time'];
    $config['monthly_type'] = $userInput['yearly_recurring_date'][0]['type'];

    switch ($config['monthly_type']) {
      case 'weekday':
        $config['day_occurrence'] = array_filter(array_values($userInput['yearly_recurring_date'][0]['day_occurrence']));
        $config['days'] = array_filter(array_values($userInput['yearly_recurring_date'][0]['days']));
        break;

      case 'monthday':
        $config['day_of_month'] = array_filter(array_values($userInput['yearly_recurring_date'][0]['day_of_month']));
        break;
    }

    $config['year_interval'] = $userInput['yearly_recurring_date'][0]['year_interval'];
    $config['months'] = array_filter(array_values($userInput['yearly_recurring_date'][0]['months']));

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildDiffArray(array $entity_config, array $form_config) {
    $diff = parent::buildDiffArray($entity_config, $form_config);

    if ($entity_config['type'] === 'yearly_recurring_date') {
      if (($entity_config['monthly_type'] ?? '') !== ($form_config['monthly_type'] ?? '')) {
        $diff['monthly_type'] = [
          'label' => t('Monthly Type'),
          'stored' => $entity_config['monthly_type'] ?? '',
          'override' => $form_config['monthly_type'] ?? '',
        ];
      }
      if ($entity_config['monthly_type'] === 'weekday') {
        if (($entity_config['day_occurrence'] ?? []) !== ($form_config['day_occurrence'] ?? [])) {
          $diff['day_occurrence'] = [
            'label' => t('Day Occurrence'),
            'stored' => implode(',', ($entity_config['day_occurrence'] ?? [])),
            'override' => implode(',', ($form_config['day_occurrence'] ?? [])),
          ];
        }
        if (($entity_config['days'] ?? []) !== ($form_config['days'] ?? [])) {
          $diff['days'] = [
            'label' => t('Days'),
            'stored' => implode(',', ($entity_config['days'] ?? [])),
            'override' => implode(',', ($form_config['days'] ?? [])),
          ];
        }
      }
      else {
        if (($entity_config['day_of_month'] ?? []) !== ($form_config['day_of_month'] ?? [])) {
          $diff['day_of_month'] = [
            'label' => t('Day of the Month'),
            'stored' => implode(',', ($entity_config['day_of_month'] ?? [])),
            'override' => implode(',', ($form_config['day_of_month'] ?? [])),
          ];
        }
      }

      if (($entity_config['year_interval'] ?? '') !== ($form_config['year_interval'] ?? '')) {
        $diff['year_interval'] = [
          'label' => t('Year Interval'),
          'stored' => $entity_config['year_interval'] ?? '',
          'override' => $form_config['year_interval'] ?? '',
        ];
      }
      if (($entity_config['months'] ?? []) !== ($form_config['months'] ?? [])) {
        $diff['months'] = [
          'label' => t('Months'),
          'stored' => implode(',', ($entity_config['months'] ?? [])),
          'override' => implode(',', ($form_config['months'] ?? [])),
        ];
      }
    }

    return $diff;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateInstances(array $form_data) {
    $eventsToCreate = parent::calculateInstances($form_data);
    if (empty($eventsToCreate)) {
      return [];
    }

    $recurrenceMonths = $form_data['months'] ?: [];
    $yearInterval = !empty($form_data['year_interval']) ? intval($form_data['year_interval']) : 1;
    $userTimezone = new \DateTimeZone(date_default_timezone_get());
    $recurrenceYears = static::findYearsBetweenDates($form_data['start_date'], $form_data['end_date'], $yearInterval);

    foreach ($eventsToCreate as $key => $dates) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $startDate */
      $startDate = clone $dates['start_date'];
      $startDate->setTimezone($userTimezone);

      $month = $startDate->format('M');
      $year = $startDate->format('Y');
      if (!in_array($month, $recurrenceMonths) || !in_array($year, $recurrenceYears)) {
        unset($eventsToCreate[$key]);
      }
    }

    return $eventsToCreate;
  }

  /**
   * Find all recurrence years between two dates.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $startDate
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime $endDate
   *   The end date.
   * @param int $interval
   *   The number of years between occurrences.
   *
   * @return array
   *   An array of matching years.
   */
  public static function findYearsBetweenDates(DrupalDateTime $startDate, DrupalDateTime $endDate, int $interval = 1) {
    $years = [];
    $userTimezone = new \DateTimeZone(date_default_timezone_get());

    $start = clone $startDate;
    $start->setTimezone($userTimezone);
    $startYear = intval($start->format('Y'));

    $end = clone $endDate;
    $end->setTimezone($userTimezone);
    $endYear = intval($end->format('Y'));

    for ($year = $startYear; $year <= $endYear; $year += $interval) {
      $years[] = $year;
    }
    return $years;
  }

}
