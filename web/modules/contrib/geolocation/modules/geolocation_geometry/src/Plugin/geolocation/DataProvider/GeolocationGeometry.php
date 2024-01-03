<?php

namespace Drupal\geolocation_geometry\Plugin\geolocation\DataProvider;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\geolocation\DataProviderBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\geolocation\DataProviderInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;

/**
 * Provides GPX.
 *
 * @DataProvider(
 *   id = "geolocation_geometry",
 *   name = @Translation("Geolocation Geometry"),
 *   description = @Translation("Points, Polygons, Polyines."),
 * )
 */
class GeolocationGeometry extends DataProviderBase implements DataProviderInterface {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings(): array {
    $settings = parent::defaultSettings();

    $settings['color_randomize'] = TRUE;

    $settings['stroke_color'] = '#FF0044';
    $settings['stroke_width'] = 1;
    $settings['stroke_opacity'] = 0.8;

    $settings['fill_color'] = '#0033FF';
    $settings['fill_opacity'] = 0.1;

    return $settings;

  }

  /**
   * {@inheritdoc}
   */
  public function isViewsGeoOption(FieldPluginBase $viewsField): bool {
    if (
      $viewsField instanceof EntityField
      && $viewsField->getPluginId() == 'field'
    ) {
      $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($viewsField->getEntityType());
      if (!empty($field_storage_definitions[$viewsField->field])) {
        $field_storage_definition = $field_storage_definitions[$viewsField->field];

        if (in_array($field_storage_definition->getType(), [
          'geolocation_geometry_geometry',
          'geolocation_geometry_geometrycollection',
          'geolocation_geometry_point',
          'geolocation_geometry_linestring',
          'geolocation_geometry_polygon',
          'geolocation_geometry_multipoint',
          'geolocation_geometry_multilinestring',
          'geolocation_geometry_multipolygon',
        ])) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []): array {
    $element = parent::getSettingsForm($settings, $parents);

    $settings = $this->getSettings($settings);

    $element['color_randomize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Randomize colors'),
      '#description' => $this->t('Set stroke and fill color to the same random value. Enabling ignores any set specific color values.'),
      '#default_value' => $settings['color_randomize'],
    ];

    $element['stroke_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Stroke color'),
      '#default_value' => $settings['stroke_color'],
    ];

    $element['stroke_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Stroke Width'),
      '#description' => $this->t('Width of the stroke in pixels.'),
      '#default_value' => $settings['stroke_width'],
    ];

    $element['stroke_opacity'] = [
      '#type' => 'number',
      '#step' => 0.01,
      '#title' => $this->t('Stroke Opacity'),
      '#description' => $this->t('Opacity of the stroke from 1 = fully visible, 0 = complete see through.'),
      '#default_value' => $settings['stroke_opacity'],
    ];

    $element['fill_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Fill color'),
      '#default_value' => $settings['fill_color'],
    ];

    $element['fill_opacity'] = [
      '#type' => 'number',
      '#step' => 0.01,
      '#title' => $this->t('Fill Opacity'),
      '#description' => $this->t('Opacity of the polygons from 1 = fully visible, 0 = complete see through.'),
      '#default_value' => $settings['fill_opacity'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationsFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array {
    $locations = parent::getLocationsFromViewsRow($row, $viewsField);

    $current_style = $viewsField->displayHandler->getPlugin('style');

    if (
      empty($current_style)
      || !is_subclass_of($current_style, 'Drupal\geolocation\Plugin\views\style\GeolocationStyleBase')
    ) {
      return $locations;
    }

    foreach ($locations as &$location) {
      if (!is_array($location)) {
        continue;
      }
      $location['#title'] = $current_style->getTitleField($row);
      $location['#label'] = $current_style->getLabelField($row);
    }

    return $locations;
  }

  /**
   * {@inheritdoc}
   */
  public function getShapesFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array {
    $shapes = parent::getShapesFromViewsRow($row, $viewsField);

    if (empty($shapes)) {
      return $shapes;
    }

    $current_style = $viewsField->displayHandler->getPlugin('style');

    if (
      empty($current_style)
      || !is_subclass_of($current_style, 'Drupal\geolocation\Plugin\views\style\GeolocationStyleBase')
    ) {
      return $shapes;
    }

    foreach ($shapes as &$shape) {
      if (!is_array($shape)) {
        continue;
      }
      $shape['#title'] = $current_style->getTitleField($row);
    }

    return $shapes;
  }

  /**
   * {@inheritdoc}
   */
  public function getShapesFromItem(FieldItemInterface $fieldItem): array {
    $settings = $this->getSettings();

    $geometries = [];

    foreach ($this->getShapesFromGeoJson($fieldItem->get('geojson')->getString()) as $shapeElement) {
      $random_color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
      switch ($shapeElement->type) {
        case 'Polygon':
          $geometry = [
            'type' => 'polygon',
            'points' => [],
          ];
          foreach ($shapeElement->coordinates[0] as $coordinate) {
            $geometry['points'][] = ['lat' => $coordinate[1], 'lng' => $coordinate[0]];
          }

          $geometries[] = [
            '#type' => 'geolocation_map_geometry',
            '#geometry' => $geometry,
            '#stroke_color' => $settings['color_randomize'] ? $random_color : $settings['stroke_color'],
            '#stroke_width' => (int) $settings['stroke_width'],
            '#stroke_opacity' => (float) $settings['stroke_opacity'],
            '#fill_color' => $settings['color_randomize'] ? $random_color : $settings['fill_color'],
            '#fill_opacity' => (float) $settings['fill_opacity'],
          ];
          break;

        case 'MultiPolygon':
          $geometry = [
            'type' => 'multipolygon',
            'polygons' => [],
          ];
          foreach ($shapeElement->coordinates as $current_polygon) {
            $polygon = [
              'type' => 'polygon',
              'points' => [],
            ];
            foreach ($current_polygon[0] as $coordinate) {
              $polygon['points'][] = ['lat' => $coordinate[1], 'lng' => $coordinate[0]];
            }
            $geometry['polygons'][] = $polygon;
          }
          $geometries[] = [
            '#type' => 'geolocation_map_geometry',
            '#geometry' => $geometry,
            '#geometry_type' => 'multipolygon',
            '#stroke_color' => $settings['color_randomize'] ? $random_color : $settings['stroke_color'],
            '#stroke_width' => (int) $settings['stroke_width'],
            '#stroke_opacity' => (float) $settings['stroke_opacity'],
            '#fill_color' => $settings['color_randomize'] ? $random_color : $settings['fill_color'],
            '#fill_opacity' => (float) $settings['fill_opacity'],
          ];
          break;

        case 'LineString':
          $geometry = [
            'type' => 'line',
            'points' => [],
          ];
          foreach ($shapeElement->coordinates as $coordinate) {
            $geometry['points'][] = ['lat' => $coordinate[1], 'lng' => $coordinate[0]];
          }

          $geometries[] = [
            '#type' => 'geolocation_map_geometry',
            '#$geometry' => $geometry,
            '#stroke_color' => $settings['color_randomize'] ? $random_color : $settings['stroke_color'],
            '#stroke_width' => (int) $settings['stroke_width'],
            '#stroke_opacity' => (float) $settings['stroke_opacity'],
          ];
          break;

        case 'MultiLineString':
          $geometry = [
            'type' => 'multiline',
            'lines' => [],
          ];
          foreach ($shapeElement->coordinates as $current_line) {
            $line = [
              'type' => 'line',
              'points' => [],
            ];
            foreach ($current_line as $coordinate) {
              $line['points'][] = ['lat' => $coordinate[1], 'lng' => $coordinate[0]];
            }
            $geometry['lines'][] = $line;
          }
          $geometries[] = [
            '#type' => 'geolocation_map_geometry',
            '#geometry' => $geometry,
            '#stroke_color' => $settings['color_randomize'] ? $random_color : $settings['stroke_color'],
            '#stroke_width' => (int) $settings['stroke_width'],
            '#stroke_opacity' => (float) $settings['stroke_opacity'],
          ];
          break;
      }
    }

    return $geometries;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationsFromItem(FieldItemInterface $fieldItem): array {
    $positions = [];

    foreach ($this->getLocationsFromGeoJson($fieldItem->get('geojson')->getString()) as $location) {

      switch ($location->type) {
        case 'Point':
          $position = [
            '#type' => 'geolocation_map_location',
            '#coordinates' => [
              'lat' => $location->coordinates[1],
              'lng' => $location->coordinates[0],
            ],
          ];
          $positions[] = $position;
          break;

        case 'MultiPoint':
          $container = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'geolocation-multipoint',
              ],
            ],
          ];
          foreach ($location->coordinates as $key => $point) {
            $position = [
              '#type' => 'geolocation_map_location',
              '#coordinates' => [
                'lat' => $point->coordinates[1],
                'lng' => $point->coordinates[0],
              ],
            ];
            $container[$key] = $position;
          }
          $positions[] = $container;
          break;
      }
    }

    return $positions;
  }

  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition): bool {
    return (in_array($fieldDefinition->getType(), [
      'geolocation_geometry_geometry',
      'geolocation_geometry_geometrycollection',
      'geolocation_geometry_point',
      'geolocation_geometry_linestring',
      'geolocation_geometry_polygon',
      'geolocation_geometry_multipoint',
      'geolocation_geometry_multilinestring',
      'geolocation_geometry_multipolygon',
    ]));
  }

  protected function getShapesFromGeoJson(string $geoJson): array {
    $shapes = [];

    $json = json_decode($geoJson);

    if (
      is_object($json)
      && isset($json->type)
    ) {
      $json = [$json];
    }

    foreach ($json as $entry) {
      if (empty($entry->type)) {
        continue;
      }
      switch ($entry->type) {
        case 'FeatureCollection':
          if (empty($entry->features)) {
            continue 2;
          }
          $shapes = array_merge($shapes, $this->getShapesFromGeoJson(is_string($entry->features) ?: json_encode($entry->features)));
          break;

        case 'Feature':
          if (empty($entry->geometry)) {
            continue 2;
          }
          $shapes = array_merge($shapes, $this->getShapesFromGeoJson(is_string($entry->geometry) ?: json_encode($entry->geometry)));
          break;

        case 'GeometryCollection':
          if (empty($entry->geometries)) {
            continue 2;
          }
          $shapes = array_merge($shapes, $this->getShapesFromGeoJson(is_string($entry->geometries) ?: json_encode($entry->geometries)));
          break;

        case 'MultiPolygon':
        case 'Polygon':
        case 'MultiLineString':
        case 'LineString':
          if (empty($entry->coordinates)) {
            continue 2;
          }
          $shapes[] = $entry;
          break;
      }
    }

    return $shapes;
  }

  protected function getLocationsFromGeoJson(string $geoJson): array {
    $locations = [];

    $json = json_decode($geoJson);

    if (
      is_object($json)
      && isset($json->type)
    ) {
      $json = [$json];
    }

    foreach ($json as $entry) {
      if (empty($entry->type)) {
        continue;
      }
      switch ($entry->type) {
        case 'FeatureCollection':
          if (empty($entry->features)) {
            continue 2;
          }
          $locations = array_merge($locations, $this->getShapesFromGeoJson(is_string($entry->features) ?: json_encode($entry->features)));
          break;

        case 'Feature':
          if (empty($entry->geometry)) {
            continue 2;
          }
          $locations = array_merge($locations, $this->getShapesFromGeoJson(is_string($entry->geometry) ?: json_encode($entry->geometry)));
          break;

        case 'GeometryCollection':
          if (empty($entry->geometries)) {
            continue 2;
          }
          $locations = array_merge($locations, $this->getShapesFromGeoJson(is_string($entry->geometries) ?: json_encode($entry->geometries)));
          break;

        case 'MultiPoint':
        case 'Point':
        if (empty($entry->coordinates)) {
            continue 2;
          }
          $locations[] = $entry;
          break;
      }
    }

    return $locations;
  }

}
