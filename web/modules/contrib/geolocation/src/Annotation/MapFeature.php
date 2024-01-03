<?php

namespace Drupal\geolocation\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a MapFeature annotation object.
 *
 * @see \Drupal\geolocation\MapFeatureManager
 * @see plugin_api
 *
 * @Annotation
 */
class MapFeature extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The name of the MapFeature.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $name;

  /**
   * The description of the MapFeature.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $description;

  /**
   * The map type supported by this MapFeature.
   *
   * @var string
   */
  public string $type;

}
