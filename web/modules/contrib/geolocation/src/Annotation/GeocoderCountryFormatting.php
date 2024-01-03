<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a GeocoderCountryFormatting annotation object.
 *
 * @see \Drupal\geolocation\GeocoderCountryFormattingManager
 * @see plugin_api
 *
 * @Annotation
 */
class GeocoderCountryFormatting extends Plugin {

  /**
   * The ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The country code.
   *
   * @var string
   */
  public string $countryCode;

  /**
   * The geocoder ID.
   *
   * @var string
   */
  public string $geocoder;

}
