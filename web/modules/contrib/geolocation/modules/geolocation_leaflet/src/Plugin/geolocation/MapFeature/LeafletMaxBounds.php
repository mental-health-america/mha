<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;


use Drupal\geolocation\MapFeatureBase;
use Drupal\geolocation\MapProviderInterface;

/**
 * Provides Leaflet.
 *
 * @MapFeature(
 *   id = "leaflet_max_bounds",
 *   name = @Translation("Max Bounds"),
 *   description = @Translation("Restrict map to set bounds."),
 *   type = "leaflet",
 * )
 */
class LeafletMaxBounds extends MapFeatureBase {

  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'north' => '',
        'south' => '',
        'east' => '',
        'west' => '',
      ]
    );
  }

  public function getSettingsForm(array $settings, array $parents = [], MapProviderInterface $mapProvider = NULL): array {
    $form['north'] = [
      '#type' => 'textfield',
      '#title' => $this->t('North'),
      '#size' => 15,
      '#default_value' => $settings['north'],
    ];
    $form['south'] = [
      '#type' => 'textfield',
      '#title' => $this->t('South'),
      '#size' => 15,
      '#default_value' => $settings['south'],
    ];
    $form['east'] = [
      '#type' => 'textfield',
      '#title' => $this->t('East'),
      '#size' => 15,
      '#default_value' => $settings['east'],
    ];
    $form['west'] = [
      '#type' => 'textfield',
      '#title' => $this->t('West'),
      '#size' => 15,
      '#default_value' => $settings['west'],
    ];

    return $form;
  }
}
