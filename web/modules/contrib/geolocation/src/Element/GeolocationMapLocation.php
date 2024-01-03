<?php

namespace Drupal\geolocation\Element;

use Drupal\Core\Render\Annotation\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Template\Attribute;

/**
 * Provides a render element for a single geolocation map location.
 *
 * Usage example:
 * @code
 * $form['location'] = [
 *   '#type' => 'geolocation_map_location',
 *   '#prefix' => $this->t('Geolocation Map Render Element'),
 *   '#description' => $this->t('Render element type "geolocation_map"'),
 *   '#title' => NULL,
 *   '#coordinates' => [
 *     'lat' => 19,
 *     'lng' => 23,
 *   ],
 *   '#id' => NULL,
 *   '#hidden' => NULL,
 *   '#icon' => NULL,
 *   '#label' => NULL,
 * ];
 * @endcode
 *
 * @FormElement("geolocation_map_location")
 */
class GeolocationMapLocation extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = get_class($this);

    return [
      '#process' => [
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
        [$this, 'preRenderLocation'],
      ],
      '#title' => NULL,
      '#coordinates' => NULL,
      '#id' => NULL,
      '#hidden' => NULL,
      '#icon' => NULL,
      '#label' => NULL,
      '#draggable' => NULL,
    ];
  }

  /**
   * Location element.
   *
   * @param array $render_array
   *   Element.
   *
   * @return array
   *   Renderable map.
   */
  public function preRenderLocation(array $render_array): array {
    $render_array['#theme'] = 'geolocation_map_location';

    if (!isset($render_array['#id'])) {
      $render_array['#id'] = uniqid('location-');
    }

    foreach (Element::children($render_array) as $child) {
      $render_array['#children'][] = $render_array[$child];
    }

    $render_array['#attributes'] = new Attribute($render_array['#attributes'] ?? []);
    $render_array['#attributes']->addClass('geolocation-location');
    $render_array['#attributes']->addClass('js-hide');
    if (!empty($render_array['#id'])) {
      $render_array['#attributes']->setAttribute('id', $render_array['#id']);
    }

    if (!empty($render_array['#coordinates'])) {
      $render_array['#attributes']->setAttribute('data-lat', $render_array['#coordinates']['lat']);
      $render_array['#attributes']->setAttribute('data-lng', $render_array['#coordinates']['lng']);
    }

    if ($render_array['#draggable'] ?? FALSE) {
      $render_array['#attributes']->setAttribute('data-draggable', 'true');
    }

    if (empty($render_array['#hidden'])) {
      $render_array['#attributes']->setAttribute('data-set-marker', 'true');

      if (!empty($render_array['#icon'])) {
        $render_array['#attributes']->setAttribute('data-icon', $render_array['#icon']);
      }

      if (!empty($render_array['#label'])) {
        $render_array['#attributes']->setAttribute('data-label', $render_array['#label']);
      }
    }

    return $render_array;
  }

}
