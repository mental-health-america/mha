<?php

namespace Drupal\geolocation\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\LocationInputManager;
use Drupal\views\Annotation\ViewsFilter;
use Drupal\views\Plugin\views\filter\NumericFilter;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geolocation\ProximityTrait;

/**
 * Filter handler for search keywords.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("geolocation_filter_proximity")
 */
class ProximityFilter extends NumericFilter implements ContainerFactoryPluginInterface {

  use ProximityTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LocationInputManager $locationInputManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProximityFilter {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.locationinput')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    // Add source, lat, lng and filter.
    $options = parent::defineOptions();

    $options['location_input'] = ['default' => []];
    $options['unit'] = ['default' => 'km'];

    $options['value']['contains']['center'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['unit'] = [
      '#title' => $this->t('Distance unit'),
      '#description' => $this->t('Unit to use for conversion of input value to proximity distance.'),
      '#type' => 'select',
      '#default_value' => $this->options['unit'],
      '#weight' => 6,
      '#options' => [
        'km' => $this->t('Kilometers'),
        'mi' => $this->t('Miles'),
        'nm' => $this->t('Nautical Miles'),
      ],
    ];

    $input = $form_state->getUserInput();
    if (!empty($input['options']['location_input'])) {
      $location_options = $input['options']['location_input'];
    }
    else {
      $location_options = $this->options['location_input'];
    }

    $form['location_input'] = $this->locationInputManager->getOptionsForm($location_options, ['views_filter' => $this], $this->t('<label class="form-item__label">Proximity - Center source</label>'));
    $form['location_input']['#weight'] = 20;
  }

  /**
   * {@inheritdoc}
   */
  public function groupForm(&$form, FormStateInterface $form_state) {
    parent::groupForm($form, $form_state);

    $center_form = $this->locationInputManager->getForm($this->options['location_input'], ['views_filter' => $this], empty($this->value['center']) ? NULL : $this->value['center']);
    if (!empty($center_form)) {
      $identifier = $this->options['expose']['identifier'];
      $form[$identifier . '_center'] = $center_form;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    if (!isset($form['value']['value'])) {
      $form['value'] = array_replace($form['value'], [
        '#type' => 'number',
        '#min' => 0,
        '#step' => 0.1,
        '#title' => $this->t('Distance'),
        '#description' => $this->t('Distance in %unit', ['%unit' => $this->options['unit'] === 'km' ? $this->t('Kilometers') : $this->t('Miles')]),
        '#default_value' => $form['value']['#default_value'],
      ]);
    }
    else {
      $form['value']['value'] = array_replace($form['value']['value'], [
        '#type' => 'number',
        '#min' => 0,
        '#step' => 0.1,
        '#title' => $this->t('Distance'),
        '#description' => $this->t('Distance in %unit', ['%unit' => $this->options['unit'] === 'km' ? $this->t('Kilometers') : $this->t('Miles')]),
        '#default_value' => $form['value']['value']['#default_value'],
      ]);
    }

    $identifier = $this->options['expose']['identifier'];

    $form[$identifier . '_center'] = $this->locationInputManager->getForm($this->options['location_input'], ['views_filter' => $this], empty($this->value['center']) ? NULL : $this->value['center'], $this->t('Center Coordinates'));
    $form[$identifier . '_center']['#tree'] = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state) {
    $distance = (float) $form_state->getValue(['options', 'value', 'value']);
    $form_state->setValue(['options', 'value', 'value'], $distance);

    $identifier = $this->options['expose']['identifier'];
    $form_state->setValue(
      ['options', $identifier . '_center'],
      $form_state->getValue(['options', $identifier . '_center'], [])
    );

    parent::valueSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function storeExposedInput($input, $status) {
    parent::storeExposedInput($input, $status);

    $identifier = $this->options['expose']['identifier'];

    if (empty($input[$identifier . '_center'])) {
      return;
    }

    $session = $this->view->getRequest()->getSession() ?? NULL;
    $views_session = $session?->get('views', []) ?? [];
    $display_id = ($this->view->display_handler->isDefaulted('filters')) ? 'default' : $this->view->current_display;

    if (empty($views_session[$this->view->storage->id()][$display_id])) {
      return;
    }

    $views_session[$this->view->storage->id()][$display_id]['center'] = $input[$identifier . '_center'];
    $session?->set('views', $views_session);
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    parent::acceptExposedInput($input);

    $this->value['center'] = [];

    $identifier = $this->options['expose']['identifier'];

    if (!empty($input[$identifier . '_center'])) {
      $this->value['center'] = $input[$identifier . '_center'];
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $table = $this->ensureMyTable();
    $this->value['value'] = self::convertDistance($this->value['value'], $this->options['unit']);

    $center = $this->locationInputManager->getCoordinates((array) $this->value['center'], $this->options['location_input'], ['views_filter' => $this]);

    if (
      empty($center)
      || !is_numeric($center['lat'])
      || !is_numeric($center['lng'])
      || empty($this->value['value'])
    ) {
      return;
    }

    // Build the query expression.
    $expression = self::getProximityQueryFragment($table, $this->realField, $center['lat'], $center['lng']);

    // Get operator info.
    $info = $this->operators();

    // Make sure a callback exists and add a where expression for the chosen
    // operator.
    if (!empty($info[$this->operator]['method']) && method_exists($this, $info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($expression);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opBetween($field) {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    if ($this->operator == 'between') {
      $query->addWhereExpression($this->options['group'], $field . ' BETWEEN ' . $this->value['min'] . ' AND ' . $this->value['max']);
    }
    else {
      $query->addWhereExpression($this->options['group'], $field . ' NOT BETWEEN ' . $this->value['min'] . ' AND ' . $this->value['max']);
    }
  }

  protected function opSimple($field) {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhereExpression($this->options['group'], $field . ' ' . $this->operator . ' ' . $this->value['value']);
  }

  protected function opEmpty($field) {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    if ($this->operator == 'empty') {
      $operator = "IS NULL";
    }
    else {
      $operator = "IS NOT NULL";
    }

    $query->addWhereExpression($this->options['group'], $field . ' ' . $operator);
  }

  /**
   * {@inheritdoc}
   */
  protected function opRegex($field) {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhereExpression($this->options['group'], $field . ' ~* ' . $this->value['value']);
  }

}
