<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\Html;
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
 * @method MapCenterInterface createInstance($plugin_id, array $configuration = [])
 */
class MapCenterManager extends DefaultPluginManager {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * Constructs an MapCenterManager object.
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
    parent::__construct('Plugin/geolocation/MapCenter', $namespaces, $module_handler, 'Drupal\geolocation\MapCenterInterface', 'Drupal\geolocation\Annotation\MapCenter');
    $this->alterInfo('geolocation_mapcenter_info');
    $this->setCacheBackend($cache_backend, 'geolocation_mapcenter');
  }

  /**
   * Return MapCenter by ID.
   *
   * @param string $id
   *   MapCenter ID.
   * @param array $configuration
   *   Configuration.
   *
   * @return \Drupal\geolocation\MapCenterInterface|null
   *   MapCenter instance.
   */
  public function getMapCenter(string $id, array $configuration = []): ?MapCenterInterface {
    if (!$this->hasDefinition($id)) {
      return NULL;
    }

    try {
      return $this->createInstance($id, $configuration);
    }
    catch (Exception $e) {
      $this->getLogger('geolocation')->warning($e->getMessage());
      return NULL;
    }
  }

  public function getCenterOptionsForm(array $settings, array $context = []): array {
    $form = [
      '#type' => 'table',
      '#prefix' => $this->t('These options allow to override the default map centre. Each option will, if it can be applied, supersede any following option.'),
      '#header' => [
        [
          'data' => $this->t('Enable'),
          'colspan' => 2,
        ],
        $this->t('Option'),
        $this->t('Settings'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'geolocation-centre-option-weight',
        ],
      ],
    ];

    foreach ($this->getDefinitions() as $map_center_id => $map_center_definition) {
      $map_center = $this->createInstance($map_center_id);
      foreach ($map_center->getAvailableMapCenterOptions($context) as $option_id => $label) {
        $option_enable_id = HTML::getUniqueId($option_id . '_enabled');
        $weight = $settings[$option_id]['weight'] ?? 0;

        $form[$option_id] = [
          '#weight' => $weight,
          '#attributes' => [
            'class' => [
              'draggable',
            ],
          ],
          'enable' => [
            '#attributes' => [
              'id' => $option_enable_id,
            ],
            '#type' => 'checkbox',
            '#default_value' => $settings[$option_id]['enable'] ?? FALSE,
          ],
          'map_center_id' => [
            '#type' => 'value',
            '#value' => $map_center_id,
          ],
          'option' => [
            '#markup' => $label,
          ],
          'settings' => [
            '#markup' => '',
          ],
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight for @option', ['@option' => $label]),
            '#title_display' => 'invisible',
            '#size' => 4,
            '#default_value' => $weight,
            '#attributes' => ['class' => ['geolocation-centre-option-weight']],
          ],
        ];

        $map_center_settings = [];
        if (!empty($settings[$option_id]['settings'])) {
          $map_center_settings = $settings[$option_id]['settings'];
        }
        $option_form = $map_center->getSettingsForm(
          $option_id,
          $map_center->getSettings($map_center_settings),
          $context
        );

        if (!empty($option_form)) {
          $option_form['#states'] = [
            'visible' => [
              ':input[id="' . $option_enable_id . '"]' => ['checked' => TRUE],
            ],
          ];
          $option_form['#type'] = 'item';

          $form[$option_id]['settings'] = $option_form;
        }
      }
    }

    uasort($form, [SortArray::class, 'sortByWeightProperty']);

    return $form;
  }

  /**
   * Alter map element.
   *
   * @param array $map
   *   Map render array.
   * @param array $settings
   *   Center option settings.
   * @param array $context
   *   Context.
   *
   * @return array
   *   Altered map render array.
   */
  public function alterMap(array $map, array $settings = [], array $context = []): array {

    uasort($settings, [SortArray::class, 'sortByWeightProperty']);

    foreach ($settings as $option_id => $option) {

      // Ignore if not enabled.
      if (empty($option['enable'])) {
        continue;
      }

      if (!$this->hasDefinition($option['map_center_id'] ?? '')) {
        continue;
      }

      $map_center_plugin = $this->createInstance($option['map_center_id']);
      $map_center_plugin_settings = $map_center_plugin->getSettings($option['settings'] ?? []);

      $map = $map_center_plugin->alterMap($map, $option_id, $option['weight'], $map_center_plugin_settings, $context);
    }

    if (empty($map['#centre'])) {
      $map['#centre'] = ['lat' => 0, 'lng' => 0];
    }

    return $map;
  }

}
