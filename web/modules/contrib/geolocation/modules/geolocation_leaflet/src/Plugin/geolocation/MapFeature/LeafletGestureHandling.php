<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;


use Drupal\geolocation\MapFeatureBase;

/**
 * Provides gesture handling.
 *
 * @MapFeature(
 *   id = "leaflet_gesture_handling",
 *   name = @Translation("Gesture Handling"),
 *   description = @Translation("Prevents map pan and zoom on page scroll. See <a target='_blank' href='https://github.com/elmarquis/Leaflet.GestureHandling'>https://github.com/elmarquis/Leaflet.GestureHandling</a>"),
 *   type = "leaflet",
 * )
 */
class LeafletGestureHandling extends MapFeatureBase {
  protected array $scripts = [
    'https://unpkg.com/leaflet-gesture-handling@1.2.2/dist/leaflet-gesture-handling.min.js',
  ];

  protected array $stylesheets = [
    'https://unpkg.com/leaflet-gesture-handling@1.2.2/dist/leaflet-gesture-handling.min.css',
  ];
}
