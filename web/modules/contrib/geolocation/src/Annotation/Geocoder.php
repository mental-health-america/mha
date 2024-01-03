<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a geocoder annotation object.
 *
 * @see \Drupal\geolocation\GeocoderManager
 * @see plugin_api
 *
 * @Annotation
 */
class Geocoder extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The name of the geocoder.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $name;

  /**
   * The description of the geocoder.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $description;

  /**
   * Can the geocoder retrieve coordinates.
   *
   * @var bool
   */
  public bool $locationCapable;

  /**
   * Can the geocoder retrieve boundaries.
   *
   * @var bool
   */
  public bool $boundaryCapable;

  /**
   * Can the geocoder be used in the frontend.
   *
   * @var bool
   */
  public bool $frontendCapable;

  /**
   * Can the geocoder perform reverse geocoding.
   *
   * @var bool
   */
  public bool $reverseCapable;

}
