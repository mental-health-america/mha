<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;


use Drupal\geolocation\MapFeatureBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\MapProviderInterface;

/**
 * Provides rotation control.
 *
 * @MapFeature(
 *   id = "leaflet_rotate",
 *   name = @Translation("Leaflet Rotate"),
 *   description = @Translation("Allow map rotation."),
 *   type = "leaflet",
 * )
 */
class LeafletRotate extends MapFeatureBase {

  protected array $scripts = [
    'https://unpkg.com/leaflet-rotate@0.1.2/dist/leaflet-rotate-src.js',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    $default_settings = parent::getDefaultSettings();

    $default_settings['bearing'] = 0;
    $default_settings['display_control'] = TRUE;

    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = [], MapProviderInterface $mapProvider = NULL): array {

    $form['display_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display control'),
      '#default_value' => $settings['display_control'],
    ];

    $form['bearing'] = [
      '#type' => 'number',
      '#min' => -360,
      '#max' => 360,
      '#step' => .01,
      '#title' => $this->t('Bearing'),
      '#description' => $this->t('Map initial rotation in degrees.'),
      '#default_value' => $settings['bearing'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings = [], array $context = [], MapProviderInterface $mapProvider = NULL): array {
    $render_array = parent::alterMap($render_array, $feature_settings, $context, $mapProvider);

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      $render_array['#attached'] ?? [],
      [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                'settings' => [
                  'rotate' => TRUE,
                ],
              ],
            ],
          ],
        ],
      ]
    );

    return $render_array;
  }

}
