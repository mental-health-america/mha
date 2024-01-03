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
 *   id = "views_boundary_argument",
 *   name = @Translation("Boundary argument - boundaries"),
 *   description = @Translation("Fit map to boundary argument."),
 * )
 */
class ViewsBoundaryArgument extends MapCenterBase implements MapCenterInterface {

  use ViewsContextTrait;

  /**
   * {@inheritdoc}
   */
  public function getAvailableMapCenterOptions(array $context = []): array {
    $options = [];

    if ($displayHandler = self::getViewsDisplayHandler($context)) {
      /** @var \Drupal\views\Plugin\views\argument\ArgumentPluginBase $argument */
      foreach ($displayHandler->getHandlers('argument') as $argument_id => $argument) {
        if ($argument->getPluginId() == 'geolocation_argument_boundary') {
          $options['boundary_argument_' . $argument_id] = $this->t('Boundary argument') . ' - ' . $argument->adminLabel();
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

    /** @var \Drupal\geolocation\Plugin\views\argument\BoundaryArgument $argument */
    $argument = $displayHandler->getHandler('argument', substr($center_option_id, 18));
    if ($values = $argument->getParsedBoundary()) {
      $render_array['#attached'] = BubbleableMetadata::mergeAttachments($render_array['#attached'], [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => [
                'mapCenter' => [
                  'views_boundary_argument' => [
                    'settings' => [
                      'latNorthEast' => (float) $values['lat_north_east'],
                      'lngNorthEast' => (float) $values['lng_north_east'],
                      'latSouthWest' => (float) $values['lat_south_west'],
                      'lngSouthWest' => (float) $values['lng_south_west'],
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
