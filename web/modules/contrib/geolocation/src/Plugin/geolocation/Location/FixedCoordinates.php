<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;


use Drupal\geolocation\LocationInterface;
use Drupal\geolocation\LocationBase;

/**
 * Fixed coordinates map center.
 *
 * PluginID for compatibility with v1.
 *
 * @Location(
 *   id = "fixed_value",
 *   name = @Translation("Fixed coordinates"),
 *   description = @Translation("Use preset fixed values as center."),
 * )
 */
class FixedCoordinates extends LocationBase implements LocationInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return [
      'latitude' => '',
      'longitude' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(string $location_option_id = NULL, array $settings = [], $context = NULL): array {
    $settings = $this->getSettings($settings);

   return [
      'latitude' => [
        '#type' => 'textfield',
        '#title' => $this->t('Latitude'),
        '#default_value' => $settings['latitude'],
        '#size' => 60,
        '#maxlength' => 128,
      ],
      'longitude' => [
        '#type' => 'textfield',
        '#title' => $this->t('Longitude'),
        '#default_value' => $settings['longitude'],
        '#size' => 60,
        '#maxlength' => 128,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates(string $location_option_id, array $location_option_settings, $context = NULL): array {
    $settings = $this->getSettings($location_option_settings);

    return [
      'lat' => (float) $settings['latitude'],
      'lng' => (float) $settings['longitude'],
    ];
  }

}
