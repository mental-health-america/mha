<?php

namespace Drupal\geolocation_google_maps;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;
use Drupal\geolocation\GeocoderInterface;
use Drupal\geolocation\GeocoderBase;
use Drupal\geolocation\GeocoderCountryFormattingManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class.
 *
 * @package Drupal\geolocation_google_places_api
 */
abstract class GoogleGeocoderBase extends GeocoderBase implements GeocoderInterface {

  /**
   * Google Maps Service.
   *
   * @var \Drupal\geolocation_google_maps\GoogleMapsService
   */
  public GoogleMapsService $googleMapsService;

  /**
   * GoogleGeocoderBase constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\geolocation\GeocoderCountryFormattingManager $geocoder_country_formatter_manager
   *   Country formatter manager.
   * @param \Drupal\geolocation_google_maps\GoogleMapsService $google_maps_service
   *   Map provider management.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeocoderCountryFormattingManager $geocoder_country_formatter_manager, ModuleHandler $module_handler, FileSystem $file_system, GoogleMapsService $google_maps_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $geocoder_country_formatter_manager, $module_handler, $file_system);

    $this->googleMapsService = $google_maps_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): GeocoderInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.geocoder_country_formatting'),
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('geolocation_google_maps.google')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultSettings(): array {
    $default_settings = parent::getDefaultSettings();

    $default_settings['component_restrictions'] = [
      'route' => '',
      'locality' => '',
      'administrative_area' => '',
      'postal_code' => '',
      'country' => '',
    ];

    $default_settings['boundary_restriction'] = [
      'south' => '',
      'west' => '',
      'north' => '',
      'east' => '',
    ];

    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $settings = parent::getSettings();

    if (!empty($settings['component_restrictions'])) {
      $settings['component_restrictions'] = array_filter($settings['component_restrictions']);

      if (isset($settings['component_restrictions']['administrative_area'])) {
        $settings['component_restrictions']['administrativeArea'] = $settings['component_restrictions']['administrative_area'];
      }

      if (isset($settings['component_restrictions']['postal_code'])) {
        $settings['component_restrictions']['postalCode'] = $settings['component_restrictions']['postal_code'];
      }
    }
    if (empty($settings['component_restrictions'])) {
      unset($settings['component_restrictions']);
    }

    if (!empty($settings['boundary_restriction'])) {
      $settings['boundary_restriction'] = array_filter($settings['boundary_restriction']);
    }
    if (empty($settings['boundary_restriction'])) {
      unset($settings['boundary_restriction']);
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsForm(): array {

    $settings = $this->getSettings();

    $form = parent::getOptionsForm();

    $form += [
      'component_restrictions' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Component Restrictions'),
        '#description' => $this->t('See <a href="https://developers.google.com/maps/documentation/geocoding/intro#ComponentFiltering">Component Filtering</a>'),
        'route' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['route'] ?? '',
          '#title' => $this->t('Route'),
          '#size' => 15,
        ],
        'locality' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['locality'] ?? '',
          '#title' => $this->t('Locality'),
          '#size' => 15,
        ],
        'administrative_area' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['administrative_area'] ?? '',
          '#title' => $this->t('Administrative Area'),
          '#size' => 15,
        ],
        'postal_code' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['postal_code'] ?? '',
          '#title' => $this->t('Postal code'),
          '#size' => 5,
        ],
        'country' => [
          '#type' => 'textfield',
          '#default_value' => $settings['component_restrictions']['country'] ?? '',
          '#title' => $this->t('Country'),
          '#description' => $this->t('Enter a comma-separated list to support multiple countries'),
          '#size' => 15,
        ],
      ],
      'boundary_restriction' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Boundary Restriction'),
        '#description' => $this->t('See <a href="https://developers.google.com/maps/documentation/geocoding/intro#Viewports">Viewports</a>'),
        'south' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['south'] ?? '',
          '#title' => $this->t('South'),
          '#size' => 15,
        ],
        'west' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['west'] ?? '',
          '#title' => $this->t('West'),
          '#size' => 15,
        ],
        'north' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['north'] ?? '',
          '#title' => $this->t('North'),
          '#size' => 15,
        ],
        'east' => [
          '#type' => 'textfield',
          '#default_value' => $settings['boundary_restriction']['east'] ?? '',
          '#title' => $this->t('East'),
          '#size' => 15,
        ],
      ],
    ];

    return $form;
  }

}
