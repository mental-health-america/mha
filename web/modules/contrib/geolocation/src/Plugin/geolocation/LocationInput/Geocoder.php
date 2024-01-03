<?php

namespace Drupal\geolocation\Plugin\geolocation\LocationInput;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;

use Drupal\geolocation\LocationInputInterface;
use Drupal\geolocation\LocationInputBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geolocation\GeocoderManager;

/**
 * Location based proximity center.
 *
 * @LocationInput(
 *   id = "geocoder",
 *   name = @Translation("Geocoder address input"),
 *   description = @Translation("Enter an address and use the geocoded location."),
 * )
 */
class Geocoder extends LocationInputBase implements LocationInputInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ModuleHandler $moduleHandler,
    FileSystem $fileSystem,
    protected GeocoderManager $geocoderManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $moduleHandler, $fileSystem);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): LocationInputInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('plugin.manager.geolocation.geocoder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    $settings = parent::getDefaultSettings();

    $settings['auto_submit'] = FALSE;
    $settings['hide_form'] = FALSE;
    $settings['plugin_id'] = '';
    $settings['geocoder_settings'] = [];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings = [], array $context = []): array {
    $form = [];

    $settings = $this->getSettings($settings);

    $form['auto_submit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-submit form'),
      '#default_value' => $settings['auto_submit'],
      '#description' => $this->t('Only triggers if location could be set'),
    ];

    $form['hide_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide coordinates form'),
      '#default_value' => $settings['hide_form'],
      '#description' => $this->t('Coordinates input will be hidden from user and filled by geocoder'),
    ];

    $geocoder_options = [];
    foreach ($this->geocoderManager->getDefinitions() as $geocoder_id => $geocoder_definition) {
      if (empty($geocoder_definition['locationCapable'])) {
        continue;
      }
      $geocoder_options[$geocoder_id] = $geocoder_definition['name'];
    }

    if ($geocoder_options) {

      $form['plugin_id'] = [
        '#type' => 'select',
        '#options' => $geocoder_options,
        '#title' => $this->t('Geocoder plugin'),
        '#default_value' => $settings['plugin_id'],
        '#ajax' => [
          'callback' => [
            get_class($this->geocoderManager), 'addGeocoderSettingsFormAjax',
          ],
          'wrapper' => 'location-input-geocoder-plugin-settings',
          'effect' => 'fade',
        ],
      ];

      if (!empty($settings['plugin_id'])) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(
          $settings['plugin_id'],
          $settings['geocoder_settings']
        );
      }
      elseif (current(array_keys($geocoder_options))) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(current(array_keys($geocoder_options)));
      }

      if (!empty($geocoder_plugin)) {
        $geocoder_settings_form = $geocoder_plugin->getOptionsForm();
        if ($geocoder_settings_form) {
          $form['geocoder_settings'] = $geocoder_settings_form;
        }
      }

      if (empty($form['geocoder_settings'])) {
        $form['geocoder_settings'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t("No settings available."),
        ];
      }

      $form['geocoder_settings'] = array_replace_recursive($form['geocoder_settings'], [
        '#flatten' => TRUE,
        '#prefix' => '<div id="location-input-geocoder-plugin-settings">',
        '#suffix' => '</div>',
      ]);
    }

    return $form;
  }

  public function getSettings(array $settings): array {
    $settings = parent::getSettings($settings);

    $settings['geocoder_settings'] = $this->geocoderManager->getGeocoder(
      $settings['plugin_id'] ?? '',
      $settings['geocoder_settings'] ?? []
    )?->getSettings();

    return $settings;
  }

  public function getCoordinates(array $form_value, array $settings, $context = NULL): array {
    $coordinates = parent::getCoordinates($form_value, $settings, $context);
    if ($coordinates) {
      return $coordinates;
    }

    if (empty($form_value['geocoder'])) {
      return [];
    }

    $settings = $this->getSettings($settings);

    $location_data = $this->geocoderManager
      ->getGeocoder($settings['plugin_id'], $settings['settings'])
      ->geocode($form_value['geocoder']['geolocation_geocoder_address']);

    if (!empty($location_data['location'])) {
      return $location_data['location'];
    }

    return [];
  }

  public function alterForm(array $form, array $settings, array $context = [], array $default_value = NULL): array {
    $form = parent::alterForm($form, $settings, $context, $default_value);

    $this->geocoderManager->getGeocoder(
      $settings['plugin_id'] ?? '',
        $settings['geocoder_settings'] ?? []
    )?->alterRenderArray($form, $form['#attributes']['data-identifier']);

    return $form;
  }

}
