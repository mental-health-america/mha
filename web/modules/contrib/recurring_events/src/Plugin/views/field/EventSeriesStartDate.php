<?php

namespace Drupal\recurring_events\Plugin\views\field;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to show the start date of an event series.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("eventseries_start_date")
 */
class EventSeriesStartDate extends FieldPluginBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The views join handler service.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $joinHandler;

  /**
   * Constructs an EventSeriesStartDate object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The current active database connection.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $join_handler
   *   The views join handler service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, Connection $database, PluginManagerInterface $join_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->joinHandler = $join_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');
    /** @var \Drupal\Core\Database\Connection $database */
    $database = $container->get('database');
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $join_handler */
    $join_handler = $container->get('plugin.manager.views.join');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $config_factory,
      $database,
      $join_handler
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Add a subquery to get the earliest instance start date for a series.
    $subQuery = $this->database->select('eventinstance_field_data', 'eventinstance_field_data');
    $subQuery->addField('eventinstance_field_data', 'eventseries_id');
    $subQuery->addExpression("MIN(eventinstance_field_data.date__value)", 'eventseries_start_date');
    $subQuery->groupBy("eventinstance_field_data.eventseries_id");

    // Create a join for the subquery.
    $joinDefinition = [
      'table formula' => $subQuery,
      'field' => 'eventseries_id',
      'left_table' => 'eventseries_field_data',
      'left_field' => 'id',
      'adjust' => TRUE,
    ];

    // Add the subquery join to the main query.
    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
    $join = $this->joinHandler->createInstance('standard', $joinDefinition);
    $this->query->addRelationship('eventseries_start_date', $join, 'eventseries_field_data');

    // Add the field to the view.
    $this->query->addField(NULL, 'eventseries_start_date', 'eventseries_start_date');
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!isset($values->eventseries_start_date)) {
      return 'N/A';
    }

    $date = new DrupalDateTime($values->eventseries_start_date, 'UTC');
    $format = $this->configFactory->get('recurring_events.eventseries.config')->get('date_format');
    return $date->format($format, [
      'timezone' => date_default_timezone_get(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    if (isset($this->field_alias)) {
      $params = $this->options['group_type'] != 'group' ? ['function' => $this->options['group_type']] : [];
      $this->query->addOrderBy(NULL, 'eventseries_start_date', $order, $this->field_alias, $params);
    }
  }

}
