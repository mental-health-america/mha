<?php

namespace Drupal\recurring_events\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'yearly recurring date' widget.
 *
 * @FieldWidget (
 *   id = "yearly_recurring_date",
 *   label = @Translation("Yearly recurring date widget"),
 *   field_types = {
 *     "yearly_recurring_date"
 *   }
 * )
 */
class YearlyRecurringDateWidget extends MonthlyRecurringDateWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#states'] = [
      'visible' => [
        ':input[name="recur_type"]' => ['value' => 'yearly_recurring_date'],
      ],
    ];
    $element['#element_validate'][] = [$this, 'validateForm'];

    $element['months'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Months'),
      '#options' => [
        'Jan' => $this->t('January'),
        'Feb' => $this->t('February'),
        'Mar' => $this->t('March'),
        'Apr' => $this->t('April'),
        'May' => $this->t('May'),
        'Jun' => $this->t('June'),
        'Jul' => $this->t('July'),
        'Aug' => $this->t('August'),
        'Sep' => $this->t('September'),
        'Oct' => $this->t('October'),
        'Nov' => $this->t('November'),
        'Dec' => $this->t('December'),
      ],
      '#default_value' => $items[$delta]->months ? explode(',', $items[$delta]->months) : [],
      '#weight' => 0,
    ];

    $element['year_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of years between occurrences'),
      '#options' => [
        '1' => $this->t('1 year'),
        '2' => $this->t('2 years'),
        '3' => $this->t('3 years'),
        '4' => $this->t('4 years'),
        '5' => $this->t('5 years'),
        '6' => $this->t('6 years'),
        '7' => $this->t('7 years'),
        '8' => $this->t('8 years'),
        '9' => $this->t('9 years'),
        '10' => $this->t('10 years'),
      ],
      '#default_value' => $items[$delta]->year_interval ?: '',
      '#weight' => 0,
    ];

    unset($element['day_occurrence']['#states']);
    unset($element['days']['#states']);
    unset($element['day_of_month']['#states']);
    unset($element['end_time']['#states']);
    unset($element['end_time']['time']['#states']);
    unset($element['duration']['#states']);
    $element['day_occurrence']['#states']['visible'][':input[name="yearly_recurring_date[0][type]"]'] = ['value' => 'weekday'];
    $element['days']['#states']['visible'][':input[name="yearly_recurring_date[0][type]"]'] = ['value' => 'weekday'];
    $element['day_of_month']['#states']['visible'][':input[name="yearly_recurring_date[0][type]"]'] = ['value' => 'monthday'];
    $element['end_time']['#states']['invisible'][':input[name="yearly_recurring_date[0][duration_or_end_time]"]'] = ['value' => 'duration'];
    $element['end_time']['time']['#states']['invisible'][':input[name="yearly_recurring_date[0][duration_or_end_time]"]'] = ['value' => 'duration'];
    $element['duration']['#states']['visible'][':input[name="yearly_recurring_date[0][duration_or_end_time]"]'] = ['value' => 'duration'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      $item['months'] = array_filter($item['months']);
      if (!empty($item['months'])) {
        $item['months'] = implode(',', $item['months']);
      }
      else {
        $item['months'] = '';
      }
    }

    return parent::massageFormValues($values, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $recur_type = $form_state->getValue('recur_type');
    if ($recur_type[0]['value'] === 'yearly_recurring_date') {
      $values = $form_state->getValue('yearly_recurring_date');
      if (empty($values[0])) {
        $form_state->setError($element, $this->t('Please configure the Yearly Recurring Date settings'));
      }
      if (!empty($values[0])) {
        $values = $values[0];

        if (empty($values['value'])) {
          $form_state->setError($element['value'], $this->t('Please enter a start date'));
        }

        if (empty($values['end_value'])) {
          $form_state->setError($element['end_value'], $this->t('Please enter an end date'));
        }

        if (empty($values['time'])) {
          $form_state->setError($element['time'], $this->t('Please enter a start time'));
        }

        if (empty($values['duration']) || !isset($complete_form['yearly_recurring_date']['widget'][0]['duration']['#options'][$values['duration']])) {
          $form_state->setError($element['duration'], $this->t('Please select a duration from the list'));
        }

        if (empty($values['type']) || !isset($complete_form['yearly_recurring_date']['widget'][0]['type']['#options'][$values['type']])) {
          $form_state->setError($element['type'], $this->t('Please select an event recurrence schedule type from the list'));
        }
        else {
          switch ($values['type']) {
            case 'weekday':
              $filtered_day_occurrences = array_filter($values['day_occurrence'], function ($value) {
                return !empty($value);
              });
              if (empty($values['day_occurrence']) || empty($filtered_day_occurrences)) {
                $form_state->setError($element['day_occurrence'], $this->t('Please select a day occurrence from the list'));
              }
              $filtered_days = array_filter($values['days'], function ($value) {
                return !empty($value);
              });
              if (empty($values['days']) || empty($filtered_days)) {
                $form_state->setError($element['days'], $this->t('Please select week days from the list'));
              }
              break;

            case 'monthday':
              $filtered_days = array_filter($values['day_of_month'], function ($value) {
                return !empty($value);
              });
              if (empty($values['day_of_month']) || empty($filtered_days)) {
                $form_state->setError($element['day_of_month'], $this->t('Please select days of the month from the list'));
              }
              break;
          }
        }

        if (empty($values['year_interval'])) {
          $form_state->setError($element['value'], $this->t('Please select the number of years between occurrences'));
        }

        if (empty($values['months'])) {
          $form_state->setError($element['value'], $this->t('Please select at least one month from the list'));
        }
      }
    }
  }

}
