<?php

namespace Drupal\geolocation\Plugin\geolocation\LocationInput;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;

use Drupal\geolocation\LocationManager;
use Drupal\geolocation\LocationInputInterface;
use Drupal\geolocation\LocationInputBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Location based proximity center.
 *
 * @LocationInput(
 *   id = "location_plugins",
 *   name = @Translation("Location Plugins"),
 *   description = @Translation("Select a location plugin."),
 * )
 */
class Location extends LocationInputBase implements LocationInputInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ModuleHandler $moduleHandler,
    FileSystem $fileSystem,
    protected LocationManager $locationManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $moduleHandler, $fileSystem);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): LocationInputInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('plugin.manager.geolocation.location')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array  {
    $settings = parent::getDefaultSettings();
    $settings['location_settings'] = [
      'settings' => [],
    ];
    $settings['location_plugin_id'] = '';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings = [], array $context = []): array {
    $values = explode(':', $location_input_option_id ?? '');
    if (count($values) !== 2) {
      return [];
    }
    $location_plugin_id = $values[0];
    $location_option_id = $values[1];

    if (!$this->locationManager->hasDefinition($location_plugin_id)) {
      return [];
    }

    $location_plugin = $this->locationManager->createInstance($location_plugin_id);
    $form = parent::getSettingsForm($settings, $context);
    // A bit more complicated to use location schema.
    $form['location_settings']['settings'] = $location_plugin->getSettingsForm($location_option_id, $settings['location_settings']['settings'], $context);
    $form['location_plugin_id'] = [
      '#type' => 'value',
      '#value' => $location_plugin_id,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableLocationInputOptions(array $context = []): array {
    $options = [];

    foreach ($this->locationManager->getDefinitions() as $location_plugin_id => $location_plugin_definition) {
      $location_plugin = $this->locationManager->createInstance($location_plugin_id);
      foreach ($location_plugin->getAvailableLocationOptions($context) as $location_id => $location_label) {
        $options[$location_plugin_id . ':' . $location_id] = $location_label;
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates(array $form_value, array $settings, array $context = NULL): array {
    $values = explode(':', $location_input_option_id ?? '');
    if (count($values) !== 2) {
      return [];
    }
    $location_plugin_id = $values[0];
    $location_option_id = $values[1];

    if (!$this->locationManager->hasDefinition($location_plugin_id)) {
      return [];
    }

    $location = $this->locationManager->createInstance($location_plugin_id);

    $center = $location->getCoordinates($location_option_id, $settings['location_settings']['settings'], $context);
    if (empty($center)) {
      return [];
    }

    return $center;
  }

}
