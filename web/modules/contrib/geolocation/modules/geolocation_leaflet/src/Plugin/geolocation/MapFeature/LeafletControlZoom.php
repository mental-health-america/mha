<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;


use Drupal\geolocation\Plugin\geolocation\MapFeature\ControlElementBase;

/**
 * Provides Zoom control element.
 *
 * @MapFeature(
 *   id = "leaflet_control_zoom",
 *   name = @Translation("Map Control - Zoom"),
 *   description = @Translation("Add buttons to zoom the map."),
 *   type = "leaflet",
 * )
 */
class LeafletControlZoom extends ControlElementBase {

}
