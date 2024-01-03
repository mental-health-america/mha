<?php

namespace Drupal\geolocation_gpx\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\Plugin\Field\FieldFormatter\GeolocationMapFormatterBase;

/**
 * Plugin implementation of the 'geofield' formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_gpx_map",
 *   module = "geolocation",
 *   label = @Translation("Geolocation GPX Formatter - Map"),
 *   field_types = {
 *     "geolocation_gpx"
 *   }
 * )
 */
class GeolocationGpxMapFormatter extends GeolocationMapFormatterBase {

  static protected string $dataProviderId = 'geolocation_gpx';

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);

    unset($element['set_marker']);
    // @todo re-enable?
    unset($element['title']);
    unset($element['info_text']);
    unset($element['replacement_patterns']);

    return $element;
  }

}
