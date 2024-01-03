<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;


use Drupal\geolocation\MapCenterInterface;
use Drupal\geolocation\MapCenterBase;

/**
 * Fixed coordinates map center.
 *
 * ID for compatibility with v1.
 *
 * @MapCenter(
 *   id = "fit_bounds",
 *   name = @Translation("Fit locations"),
 *   description = @Translation("Automatically fit map to displayed locations."),
 * )
 */
class FitLocations extends MapCenterBase implements MapCenterInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return [
      'min_zoom' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(string $option_id = NULL, array $settings = [], array $context = []): array {
    $form = parent::getSettingsForm($option_id, $settings, $context);
    $form['min_zoom'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Set a minimum zoom, especially useful when only location is centered on.'),
      '#default_value' => $settings['min_zoom'],
    ];

    return $form;
  }

}
