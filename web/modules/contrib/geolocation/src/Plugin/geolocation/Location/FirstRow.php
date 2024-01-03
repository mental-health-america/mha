<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;


use Drupal\geolocation\LocationInterface;
use Drupal\geolocation\LocationBase;
use Drupal\geolocation\ViewsContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geolocation\DataProviderManager;

/**
 * Derive center from first row.
 *
 * @Location(
 *   id = "first_row",
 *   name = @Translation("View first row"),
 *   description = @Translation("Use geolocation field value from first row."),
 * )
 */
class FirstRow extends LocationBase implements LocationInterface {

  use ViewsContextTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected DataProviderManager $dataProviderManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): LocationInterface {
    return new static (
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.dataprovider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationOptions(array $context = []): array {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      if ($displayHandler->getPlugin('style')->getPluginId() == 'maps_common') {
        $options['first_row'] = $this->t('First row');
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates(string $location_option_id, array $location_option_settings, mixed $context = NULL): ?array {
    if (!($displayHandler = self::getViewsDisplayHandler($context))) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }
    /** @var \Drupal\geolocation\Plugin\views\style\GeolocationStyleBase $views_style */
    $views_style = $displayHandler->getPlugin('style');

    if (empty($views_style->options['geolocation_field'])) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    /** @var \Drupal\geolocation\Plugin\views\field\GeolocationField|null $source_field */
    $source_field = $views_style->view->field[$views_style->options['geolocation_field']];

    if (empty($source_field)) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    if (empty($views_style->view->result[0])) {
      return parent::getCoordinates($location_option_id, $location_option_settings, $context);
    }

    /** @var \Drupal\geolocation\DataProviderInterface $data_provider */
    $data_provider = $this->dataProviderManager->getDataProviderByViewsField($source_field);

    $positions = $data_provider->getPositionsFromViewsRow($views_style->view->result[0], $source_field);

    if (!empty($positions[0])) {
      return $positions[0];
    }

    return parent::getCoordinates($location_option_id, $location_option_settings, $context);
  }

}
