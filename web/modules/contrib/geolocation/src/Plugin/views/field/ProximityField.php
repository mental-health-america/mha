<?php

namespace Drupal\geolocation\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\views\Annotation\ViewsField;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geolocation\LocationManager;
use Drupal\geolocation\ProximityTrait;

/**
 * Field handler for geolocation field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("geolocation_field_proximity")
 */
class ProximityField extends NumericField implements ContainerFactoryPluginInterface {

  use ProximityTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LocationManager $locationManager
) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProximityField {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.location')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['center'] = ['default' => []];
    $options['display_unit'] = ['default' => 'km'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['center'] = $this->locationManager->getLocationOptionsForm($this->options['center'], ['views_field' => $this]);

    $form['display_unit'] = [
      '#title' => $this->t('Distance unit'),
      '#description' => $this->t('Values internally are always treated as kilometers. This setting converts values accordingly.'),
      '#type' => 'select',
      '#weight' => 5,
      '#default_value' => $this->options['display_unit'],
      '#options' => [
        'km' => $this->t('Kilometer'),
        'mi' => $this->t('Miles'),
        'nm' => $this->t('Nautical Miles'),
        'm' => $this->t('Meter'),
        'ly' => $this->t('Light-years'),
      ],
    ];
  }

  /**
   * Get center value.
   *
   * @return array
   *   Center value.
   */
  protected function getCenter(): array {
    return $this->locationManager->getLocation($this->options['center'], ['views_filter' => $this]);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    $center = $this->getCenter();
    if (empty($center)) {
      return;
    }

    // Build the query expression.
    $expression = self::getProximityQueryFragment($this->ensureMyTable(), $this->realField, $center['lat'], $center['lng']);

    // Get a placeholder for this query and save the field_alias for it.
    // Remove the initial ':' from the placeholder and avoid collision with
    // original field name.
    $this->field_alias = $query->addField(NULL, $expression, substr($this->placeholder(), 1));
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $value = parent::getValue($values, $field);
    return self::convertDistance((float) $value, $this->options['display_unit'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): MarkupInterface|string {

    // Remove once https://www.drupal.org/node/1232920 lands.
    $value = $this->getValue($values);
    // Hiding should happen before rounding or adding prefix/suffix.
    if ($this->options['hide_empty'] && empty($value) && ($value !== 0 || $this->options['empty_zero'])) {
      return '';
    }
    return parent::render($values);
  }

}
