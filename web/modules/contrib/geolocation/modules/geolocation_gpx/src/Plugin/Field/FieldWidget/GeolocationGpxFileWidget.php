<?php

namespace Drupal\geolocation_gpx\Plugin\Field\FieldWidget;

use Drupal\file\Plugin\Field\FieldWidget\FileWidget;

/**
 * Plugin implementation of the 'geolocation_gpx_file' widget.
 *
 * @FieldWidget(
 *   id = "geolocation_gpx_file",
 *   label = @Translation("Geolocation GPX File"),
 *   field_types = {
 *     "geolocation_gpx_file"
 *   }
 * )
 */
class GeolocationGpxFileWidget extends FileWidget {

}
