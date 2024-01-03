<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;


use Drupal\geolocation\MapCenterInterface;
use Drupal\geolocation\MapCenterBase;

/**
 * Fixed boundaries map center.
 *
 * @MapCenter(
 *   id = "fixed_boundaries",
 *   name = @Translation("Fixed boundaries"),
 *   description = @Translation("Fit map to preset boundaries."),
 * )
 */
class FixedBoundaries extends MapCenterBase implements MapCenterInterface {

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return [
      'north' => NULL,
      'east' => NULL,
      'south' => NULL,
      'west' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(string $option_id = NULL, array $settings = [], array $context = []): array {
    $form = parent::getSettingsForm($option_id, $settings, $context);
    $form['north'] = [
      '#type' => 'number',
      '#title' => $this->t('Northern boundary.'),
      '#default_value' => $settings['north'],
      '#min' => -90,
      '#max' => 90,
      '#step' => 0.001,
    ];
    $form['east'] = [
      '#type' => 'number',
      '#title' => $this->t('Eastern boundary.'),
      '#default_value' => $settings['east'],
      '#min' => -180,
      '#max' => 180,
      '#step' => 0.001,
    ];
    $form['south'] = [
      '#type' => 'number',
      '#title' => $this->t('Southern boundary.'),
      '#default_value' => $settings['south'],
      '#min' => -90,
      '#max' => 90,
      '#step' => 0.001,
    ];
    $form['west'] = [
      '#type' => 'number',
      '#title' => $this->t('Western boundary.'),
      '#default_value' => $settings['west'],
      '#min' => -180,
      '#max' => 180,
      '#step' => 0.001,
    ];

    return $form;
  }

}
