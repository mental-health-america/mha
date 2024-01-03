<?php

namespace Drupal\geolocation\Plugin\geolocation\MapCenter;

use Drupal\Core\Render\BubbleableMetadata;

use Drupal\geolocation\MapCenterInterface;
use Drupal\geolocation\MapCenterBase;
use Drupal\geolocation\ViewsContextTrait;

/**
 * Derive center from boundary filter.
 *
 * @MapCenter(
 *   id = "views_boundary_filter",
 *   name = @Translation("Boundary filter"),
 *   description = @Translation("Fit map to boundary filter."),
 * )
 */
class ViewsBoundaryFilter extends MapCenterBase implements MapCenterInterface {

  use ViewsContextTrait;

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'clear_address_input' => TRUE,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(string $center_option_id = NULL, array $settings = [], array $context = []): array {
    $form = parent::getSettingsForm($center_option_id, $settings, $context);

    $form['clear_address_input'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Clear address input on map bound change.'),
      '#default_value' => $settings['clear_address_input'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableMapCenterOptions(array $context = []): array {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
      foreach ($displayHandler->getHandlers('filter') as $filter_id => $filter) {
        if ($filter->getPluginId() === 'geolocation_filter_boundary') {
          // Preserve compatibility to v1.
          $options['boundary_filter_' . $filter_id] = $this->t('Boundary filter') . ' - ' . $filter->adminLabel();
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, string $center_option_id, int $weight, array $center_option_settings = [], array $context = []): array {
    $render_array = parent::alterMap($render_array, $center_option_id, $weight, $center_option_settings, $context);

    if (!($displayHandler = self::getViewsDisplayHandler($context))) {
      return $render_array;
    }

    /** @var \Drupal\geolocation\Plugin\views\filter\BoundaryFilter $handler */
    $handler = $displayHandler->getHandler('filter', substr($center_option_id, 16));

    if (!$handler) {
      return $render_array;
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments($render_array['#attached'], [
      'drupalSettings' => [
        'geolocation' => [
          'maps' => [
            $render_array['#id'] => [
              'mapCenter' => [
                'views_boundary_filter' => [
                  'settings' => [
                    'clearAddressInput' => (bool) $center_option_settings['clear_address_input'],
                    'identifier' => $handler->options['expose']['identifier'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    if (
      isset($handler->value['lat_north_east'])
      && $handler->value['lat_north_east'] !== ""
      && isset($handler->value['lng_north_east'])
      && $handler->value['lng_north_east'] !== ""
      && isset($handler->value['lat_south_west'])
      && $handler->value['lat_south_west'] !== ""
      && isset($handler->value['lng_south_west'])
      && $handler->value['lng_south_west'] !== ""
    ) {
      $render_array['#attached'] = BubbleableMetadata::mergeAttachments($render_array['#attached'], [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                'mapCenter' => [
                  'views_boundary_filter' => [
                    'settings' => [
                      'north' => (float) $handler->value['lat_north_east'],
                      'east' => (float) $handler->value['lng_north_east'],
                      'south' => (float) $handler->value['lat_south_west'],
                      'west' => (float) $handler->value['lng_south_west'],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);
    }

    return $render_array;
  }

}
