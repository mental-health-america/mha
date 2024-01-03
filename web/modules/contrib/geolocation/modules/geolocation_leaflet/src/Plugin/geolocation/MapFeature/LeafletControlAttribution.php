<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;


use Drupal\geolocation\MapProviderInterface;
use Drupal\geolocation\Plugin\geolocation\MapFeature\ControlElementBase;

/**
 * Provides Attribution control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_attribution",
 *   name = @Translation("Map Control - Attribution"),
 *   description = @Translation("Add attribution the map."),
 *   type = "leaflet",
 * )
 */
class LeafletControlAttribution extends ControlElementBase {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'prefix' => 'Leaflet',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = [], MapProviderInterface $mapProvider = NULL): array {
    $form = parent::getSettingsForm($settings, $parents, $mapProvider);

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#description' => $this->t('The HTML text shown before the attributions.'),
      '#default_value' => $settings['prefix'],
    ];

    return $form;
  }

}
