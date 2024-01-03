<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Exception;
use Traversable;

/**
 * Search plugin manager.
 *
 * @method MapFeatureInterface createInstance($plugin_id, array $configuration = [])
 */
class MapFeatureManager extends DefaultPluginManager {

  use LoggerChannelTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Constructs an MapFeatureManager object.
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
    parent::__construct('Plugin/geolocation/MapFeature', $namespaces, $module_handler, 'Drupal\geolocation\MapFeatureInterface', 'Drupal\geolocation\Annotation\MapFeature');
    $this->alterInfo('geolocation_mapfeature_info');
    $this->setCacheBackend($cache_backend, 'geolocation_mapfeature');
  }

  public function getMapFeature(string $id, array $configuration = []): ?MapFeatureInterface {
    if (!$this->hasDefinition($id)) {
      return NULL;
    }

    try {
      return $this->createInstance($id, $configuration);
    }
    catch (Exception $e) {
      $this->getLogger('geolocation')->warning("Error loading MapFeature: " . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Return MapFeature by ID.
   *
   * @param string $type
   *   Map type.
   *
   * @return array[]
   *   Map feature list.
   */
  public function getMapFeaturesByMapType(string $type): array {
    $definitions = $this->getDefinitions();
    $list = [];
    foreach ($definitions as $id => $definition) {
      if ($definition['type'] == $type || $definition['type'] == 'all') {
        $list[$id] = $definition;
      }
    }

    uasort($list, [self::class, 'sortByName']);

    return $list;
  }

  /**
   * Support sorting function.
   *
   * @param array $a
   *   Element entry.
   * @param array $b
   *   Element entry.
   *
   * @return int
   *   Sorting value.
   */
  public static function sortByName(array $a, array $b): int {
    return SortArray::sortByKeyString($a, $b, 'name');
  }

  public function getOptionsForm(array $settings, array $parents = [], MapProviderInterface $map_provider = NULL): array {
    $map_features = $this->getMapFeaturesByMapType($map_provider->getPluginId());

    if (empty($map_features)) {
      return [];
    }

    $map_features_form = [
      '#type' => 'table',
      '#weight' => 100,
      '#prefix' => $this->t('Select features to extend functionality of your map.'),
      '#header' => [
        $this->t('Enable'),
        $this->t('Feature'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'geolocation-map-feature-option-weight',
        ],
      ],
      '#parents' => $parents,
    ];
    $map_features_form['#element_validate'][] = [
      $this, 'validateMapFeatureForms',
    ];

    foreach ($map_features as $feature_id => $feature_definition) {
      $feature = $this->getMapFeature($feature_id);
      if (empty($feature)) {
        continue;
      }

      $feature_enable_id = Html::getUniqueId($feature_id . '_enabled');
      $weight = $settings[$feature_id]['weight'] ?? 0;

      $feature_settings = $settings[$feature_id]['settings'] ?? [];
      if (!is_array($feature_settings)) {
        $feature_settings = [$feature_settings];
      }

      $map_features_form[$feature_id] = [
        '#weight' => $weight,
        '#attributes' => [
          'class' => [
            'draggable',
          ],
        ],
        'enabled' => [
          '#attributes' => [
            'id' => $feature_enable_id,
          ],
          '#type' => 'checkbox',
          '#default_value' => !empty($settings[$feature_id]['enabled']),
          '#wrapper_attributes' => ['style' => 'vertical-align: top'],
        ],
        'feature' => [
          'label' => [
            '#type' => 'label',
            '#title' => $feature_definition['name'],
            '#suffix' => $feature_definition['description'],
          ],
          '#wrapper_attributes' => ['style' => 'vertical-align: top'],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @option', ['@option' => $feature_definition['name']]),
          '#title_display' => 'invisible',
          '#size' => 4,
          '#default_value' => $weight,
          '#attributes' => ['class' => ['geolocation-map-feature-option-weight']],
        ],
      ];

      $feature_form = $feature->getSettingsForm(
        $feature->getSettings($feature_settings, $map_provider),
        array_merge($parents, [$feature_id, 'settings']),
        $map_provider
      );

      if ($feature_form) {
        $feature_form['#states'] = [
          'visible' => [
            ':input[id="' . $feature_enable_id . '"]' => ['checked' => TRUE],
          ],
        ];
        $feature_form['#type'] = 'item';

        $map_features_form[$feature_id]['feature']['settings'] = $feature_form;
      }
    }

    uasort($map_features_form, [SortArray::class, 'sortByWeightProperty']);

    return $map_features_form;
  }

  public function validateMapFeatureForms(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $parents = [];
    if (!empty($element['#parents'])) {
      $parents = $element['#parents'];
      $values = NestedArray::getValue($values, $parents);
    }

    foreach ($values as $feature_id => $feature_settings) {
      if (!$feature_settings['enabled']) {
        continue;
      }

      $feature = $this->getMapFeature($feature_id);
      if ($feature && method_exists($feature, 'validateSettingsForm')) {
        $feature_parents = $parents;
        array_push($feature_parents, $feature_id, 'settings');
        $feature->validateSettingsForm($feature_settings['settings'] ?? [], $form_state, $feature_parents);
      }
    }
  }

}
