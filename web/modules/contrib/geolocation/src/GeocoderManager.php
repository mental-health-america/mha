<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Exception;
use Traversable;

/**
 * Search plugin manager.
 *
 * @method GeocoderInterface createInstance($plugin_id, array $configuration = [])
 */
class GeocoderManager extends DefaultPluginManager {

  /**
   * Constructs an GeocoderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/geolocation/Geocoder', $namespaces, $module_handler, 'Drupal\geolocation\GeocoderInterface', 'Drupal\geolocation\Annotation\Geocoder');
    $this->alterInfo('geolocation_geocoder_info');
    $this->setCacheBackend($cache_backend, 'geolocation_geocoder');
  }

  /**
   * Return Geocoder by ID.
   */
  public function getGeocoder(string $id, array $configuration = []): ?GeocoderInterface {
    if (!$this->hasDefinition($id)) {
      return NULL;
    }

    try {
      return $this->createInstance($id, $configuration);
    }
    catch (Exception $e) {
      return NULL;
    }
  }

  /**
   * Return settings array for geocoder after select change.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current From State.
   *
   * @return array
   *   Settings form.
   */
  public static function addGeocoderSettingsFormAjax(array $form, FormStateInterface $form_state): array {
    $triggering_element_parents = $form_state->getTriggeringElement()['#array_parents'];

    $settings_element_parents = $triggering_element_parents;
    array_splice($settings_element_parents, -1, 1, 'geocoder_settings');
    $old_form = NestedArray::getValue($form, $settings_element_parents);

    /** @var \Drupal\geolocation\GeocoderInterface $geocoder */
    $geocoder = \Drupal::service('plugin.manager.geolocation.geocoder')->getGeocoder($form_state->getTriggeringElement()['#value']);

    return NestedArray::mergeDeep($geocoder->getOptionsForm(), [
      '#prefix' => $old_form['#prefix'],
      '#suffix' => $old_form['#suffix'],
    ]);
  }

}
