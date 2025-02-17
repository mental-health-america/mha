<?php

namespace Drupal\fraction\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\fraction\Fraction as FractionClass;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler for Fraction database columns.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("fraction")
 */
class Fraction extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Default to automatic precision.
    $options['precision'] = ['default' => 0];
    $options['auto_precision'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    // Add fields for configuring precision and auto_precision.
    $form['precision'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Precision'),
      '#description' => $this->t('Specify the number of digits after the decimal place to display when converting the fraction to a decimal. When "Auto precision" is enabled, this value essentially becomes a minimum fallback precision.'),
      '#default_value' => $this->options['precision'],
    ];
    $form['auto_precision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto precision'),
      '#description' => $this->t('Automatically determine the maximum precision if the fraction has a base-10 denominator. For example, 1/100 would have a precision of 2, 1/1000 would have a precision of 3, etc.'),
      '#default_value' => $this->options['auto_precision'],
    ];

    // Merge into the parent form.
    parent::buildOptionsForm($form, $form_state);

    // Remove the 'click_sort_column' form element, because we provide a custom
    // click_sort function below to use the numerator and denominator columns
    // simultaneously.
    unset($form['click_sort_column']);
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    // Ensure the main table for this field is included.
    $this->ensureMyTable();

    // Formula for calculating the final value, by dividing numerator by
    // denominator.
    // These are available as additional fields.
    $numerator = $this->tableAlias . '.' . $this->definition['additional fields']['numerator'];
    $denominator = $this->tableAlias . '.' . $this->definition['additional fields']['denominator'];
    // Multiply the numerator field by 1.0 so the database returns a decimal
    // from the computation.
    $formula = '1.0 * ' . $numerator . ' / ' . $denominator;

    // Add the orderby.
    $this->query->addOrderBy(NULL, $formula, $order, $this->tableAlias . '_decimal');
  }

  /**
   * Loads the numerator and denominator values and converts to decimal.
   */
  public function getValue(ResultRow $values, $field = NULL) {
    // Find the numerator and denominator field aliases.
    $numerator_alias = $this->aliases[$this->definition['additional fields']['numerator']];
    $denominator_alias = $this->aliases[$this->definition['additional fields']['denominator']];

    // If both values are available...
    if (isset($values->{$numerator_alias}) && isset($values->{$denominator_alias})) {

      // Convert to decimal.
      $numerator = $values->{$numerator_alias};
      $denominator = $values->{$denominator_alias};
      $precision = $this->options['precision'];
      $auto_precision = $this->options['auto_precision'];
      $fraction = new FractionClass($numerator, $denominator);
      return $fraction->toDecimal($precision, $auto_precision);
    }
  }

}
