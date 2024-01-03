<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;


use Drupal\geolocation\MapProviderInterface;
use Drupal\geolocation\Plugin\geolocation\MapFeature\ControlElementBase;

/**
 * Provides Scale control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_scale",
 *   name = @Translation("Map Control - Scale"),
 *   description = @Translation("A simple scale control that shows the scale of the current center of screen in metric (m/km) and imperial (mi/ft) systems."),
 *   type = "leaflet",
 * )
 */
class LeafletControlScale extends ControlElementBase {

  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'metric' => TRUE,
        'imperial' => TRUE,
      ]
    );
  }

  public function getSettingsForm(array $settings, array $parents = [], MapProviderInterface $mapProvider = NULL): array {
    $form = parent::getSettingsForm($settings, $parents, $mapProvider);

    $form['metric'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Metric'),
      '#description' => $this->t('Whether to show the metric scale line (m/km).'),
      '#default_value' => $settings['metric'],
    ];
    $form['imperial'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Imperial'),
      '#description' => $this->t('Whether to show the imperial scale line (mi/ft).'),
      '#default_value' => $settings['imperial'],
    ];

    return $form;
  }
}
