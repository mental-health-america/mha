<?php

namespace Drupal\geolocation;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\SortArray;

/**
 * Provide Map Provider Base class.
 *
 * @package Drupal\geolocation
 */
abstract class MapProviderBase extends PluginBase implements MapProviderInterface, ContainerFactoryPluginInterface {

  protected array $scripts = [];
  protected array $stylesheets = [];

  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    protected MapFeatureManager $mapFeatureManager,
    protected ModuleHandler $moduleHandler,
    protected FileSystem $fileSystem
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $class_name = (new ReflectionClass($this))->getShortName();

    $module_path = $this->moduleHandler->getModule($this->getPluginDefinition()['provider'])->getPath();

    if (file_exists($this->fileSystem->realpath($module_path) . '/css/MapProvider/' . $class_name . '.css')) {
      $this->stylesheets[] = base_path() . $module_path . '/css/MapProvider/' . $class_name . '.css';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): MapProviderInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.mapfeature'),
      $container->get('module_handler'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return [
      'conditional_initialization' => 'no',
      'conditional_description' => t('Clicking this button will embed a map.'),
      'conditional_label' => t('Show map'),
      'map_features' => [],
      'data_layers' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(array $settings): array {
    $default_settings = $this->getDefaultSettings();
    $settings = array_replace_recursive($default_settings, $settings);

    foreach ($settings as $key => $setting) {
      if (!isset($default_settings[$key])) {
        unset($settings[$key]);
      }
    }

    foreach ($this->mapFeatureManager->getMapFeaturesByMapType($this->getPluginId()) as $feature_id => $feature_definition) {
      if (!empty($settings['map_features'][$feature_id]['enabled'])) {
        $feature = $this->mapFeatureManager->getMapFeature($feature_id);
        if ($feature) {
          if (empty($settings['map_features'][$feature_id]['settings'])) {
            $settings['map_features'][$feature_id]['settings'] = $feature->getSettings([], $this);
          }
          else {
            $settings['map_features'][$feature_id]['settings'] = $feature->getSettings($settings['map_features'][$feature_id]['settings'], $this);
          }
        }
        else {
          unset($settings['map_features'][$feature_id]);
        }
      }
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $settings): array {
    $summary = [$this->getPluginDefinition()['name']];
    foreach ($this->mapFeatureManager->getMapFeaturesByMapType($this->getPluginId()) as $feature_id => $feature_definition) {
      if (!empty($settings['map_features'][$feature_id]['enabled'])) {
        $feature = $this->mapFeatureManager->getMapFeature($feature_id);
        if ($feature) {
          if (!empty($settings['map_features'][$feature_id]['settings'])) {
            $feature_settings = $settings['map_features'][$feature_id]['settings'];
          }
          else {
            $feature_settings = $feature->getSettings([], $this);
          }
          $summary = array_merge(
            $summary,
            $feature->getSettingsSummary($feature_settings, $this)
          );
        }
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []): array {
    $states_prefix = reset($parents) . '[' . implode('][', $parents) . ']';

    $form = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('%map_provider settings', ['%map_provider' => $this->pluginDefinition['name']]),
      '#description' => $this->t('Additional map settings provided by %map_provider', ['%map_provider' => $this->pluginDefinition['name']]),
    ];

    $form['conditional_initialization'] = [
      '#type' => 'select',
      '#options' => [
        'no' => $this->t('No'),
        'button' => $this->t('Yes, show button'),
        'programmatically' => $this->t('Yes, on custom code'),
      ],
      '#default_value' => $settings['conditional_initialization'],
      '#title' => $this->t('Conditional initialization'),
      '#description' => $this->t('Delay map initialization on specific conditions. This is required for GDPR / DSGVO / CMP compliance! <br /> Call `Drupal.geolocation.delayedMaps.init()` to trigger.'),
    ];

    $form['conditional_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Conditional Description'),
      '#default_value' => $settings['conditional_description'],
      '#size' => 60,
      '#states' => [
        'visible' => [
          ':input[name="' . $states_prefix . '[conditional_initialization]"]' => ['value' => 'button'],
        ],
      ],
    ];
    $form['conditional_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Conditional Button Label'),
      '#default_value' => $settings['conditional_label'],
      '#size' => 60,
      '#states' => [
        'visible' => [
          ':input[name="' . $states_prefix . '[conditional_initialization]"]' => ['value' => 'button'],
        ],
      ],
    ];

    if ($this->mapFeatureManager->getMapFeaturesByMapType($this->getPluginId())) {
      $form['map_features'] = [
        '#type' => 'details',
        '#title' => $this->t('Map features'),
        '#weight' => 2,
        'form' => $this->mapFeatureManager->getOptionsForm($settings['map_features'], array_merge($parents, ['map_features']), $this),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRenderArray(array $render_array, array $map_settings, array $context = []): array {

    $map_settings['import_path'] = $this->getJavascriptModulePath();
    $map_settings['scripts'] = $this->scripts;
    $map_settings['stylesheets'] = $this->stylesheets;

    if (empty($map_settings['data_layers']['default'])) {
      $map_settings['data_layers']['default'] = [];
    }
    if (empty($map_settings['data_layers']['default']['import_path'])) {
      $map_settings['data_layers']['default']['import_path'] = base_path() . $this->moduleHandler->getModule('geolocation')->getPath() . '/js/DataLayer/DefaultLayer.js';
    }
    if (empty($map_settings['data_layers']['default']['name'])) {
      $map_settings['data_layers']['default']['name'] = $this->t('Default');
    }

    $render_array['#attached'] = BubbleableMetadata::mergeAttachments(
      $render_array['#attached'] ?? [],
      [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $render_array['#id'] => $map_settings,
            ],
          ],
        ],
      ]
    );

    if (!empty($map_settings['map_features'])) {
      uasort($map_settings['map_features'], [SortArray::class, 'sortByWeightElement']);

      foreach ($map_settings['map_features'] as $feature_id => $feature_settings) {
        if (!empty($feature_settings['enabled'])) {
          $feature = $this->mapFeatureManager->getMapFeature($feature_id);
          if ($feature) {
            if (empty($feature_settings['settings'])) {
              $feature_settings['settings'] = [];
            }
            $render_array = $feature->alterMap($render_array, $feature->getSettings($feature_settings['settings']), $context, $this);
          }
        }
      }

      unset($map_settings['map_features']);
    }

    return $render_array;
  }

  /**
   * {@inheritdoc}
   */
  public static function getControlPositions(): array {
    return [];
  }

  public function getJavascriptModulePath() : ?string {
    $class_name = (new ReflectionClass($this))->getShortName() ?? '';

    $module_path = $this->moduleHandler->getModule($this->getPluginDefinition()['provider'])->getPath();

    if (!file_exists($this->fileSystem->realpath($module_path) . '/js/MapProvider/' . $class_name . '.js')) {
      return NULL;
    }

    return base_path() . $module_path . '/js/MapProvider/' . $class_name . '.js';
  }

}
