<?php

namespace Drupal\geolocation\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Annotation\FieldFormatter;

/**
 * Plugin implementation of the 'geolocation' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_map",
 *   module = "geolocation",
 *   label = @Translation("Geolocation Formatter - Map"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationMapFormatter extends GeolocationMapFormatterBase {

  /**
   * {@inheritdoc}
   */
  static protected string $dataProviderId = 'geolocation_field_provider';

}
