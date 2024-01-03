<?php

namespace Drupal\geolocation_google_maps;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;
use Drupal\geolocation\DataLayerProviderManager;
use Drupal\geolocation\MapFeatureManager;
use Drupal\geolocation\MapProviderBase;
use Drupal\geolocation\MapProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GoogleMapsProvider Base.
 *
 * @package Drupal\geolocation_google_maps
 */
abstract class GoogleMapsProviderBase extends MapProviderBase {

  /**
   * Google map style - Roadmap.
   *
   * @var string
   */
  public static string $roadmap = 'ROADMAP';

  /**
   * Google map style - Satellite.
   *
   * @var string
   */
  public static string $satellite = 'SATELLITE';

  /**
   * Google map style - Hybrid.
   *
   * @var string
   */
  public static string $hybrid = 'HYBRID';

  /**
   * Google map style - Terrain.
   *
   * @var string
   */
  public static string $terrain = 'TERRAIN';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MapFeatureManager $map_feature_manager,
    ModuleHandler $module_handler,
    FileSystem $file_system,
    public GoogleMapsService $googleMapsService
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $map_feature_manager, $module_handler, $file_system);
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
      $container->get('file_system'),
      $container->get('geolocation_google_maps.google')
    );
  }

  /**
   * An array of all available map types.
   *
   * @return array
   *   The map types.
   */
  private function getMapTypes(): array {
    $mapTypes = [
      static::$roadmap => 'Road map view',
      static::$satellite => 'Google Earth satellite images',
      static::$hybrid => 'A mixture of normal and satellite views',
      static::$terrain => 'A physical map based on terrain information',
    ];

    return array_map([$this, 't'], $mapTypes);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'type' => static::$roadmap,
        'zoom' => 10,
        'height' => '400px',
        'width' => '100%',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(array $settings): array {
    $settings = parent::getSettings($settings);

    $settings['zoom'] = (int) $settings['zoom'];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSummary(array $settings): array {
    $types = $this->getMapTypes();
    $settings = $this->getSettings($settings);
    $summary = parent::getSettingsSummary($settings);
    $summary[] = $this->t('Map Type: @type', ['@type' => $types[$settings['type']] ?? '']);
    $summary[] = $this->t('Zoom level: @zoom', ['@zoom' => $settings['zoom']]);
    $summary[] = $this->t('Height: @height', ['@height' => $settings['height']]);
    $summary[] = $this->t('Width: @width', ['@width' => $settings['width']]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = []): array {
    $settings = $this->getSettings($settings);
    $parents_string = '';
    if ($parents) {
      $parents_string = implode('][', $parents) . '][';
    }

    $form = parent::getSettingsForm($settings, $parents);

    /*
     * General settings.
     */
    $form['general_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General'),
    ];
    $form['height'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['height'],
    ];
    $form['width'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Enter the dimensions and the measurement units. E.g. 200px or 100%.'),
      '#size' => 4,
      '#default_value' => $settings['width'],
    ];
    $form['type'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'select',
      '#title' => $this->t('Default map type'),
      '#options' => $this->getMapTypes(),
      '#default_value' => $settings['type'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
        ['\Drupal\Core\Render\Element\Select', 'processSelect'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    $form['zoom'] = [
      '#group' => $parents_string . 'general_settings',
      '#type' => 'number',
      '#title' => $this->t('Zoom level'),
      '#description' => $this->t('The initial resolution at which to display the map, where zoom 0 corresponds to a map of the Earth fully zoomed out, and higher zoom levels zoom in at a higher resolution up to 20 for streetlevel.'),
      '#default_value' => $settings['zoom'],
      '#process' => [
        ['\Drupal\Core\Render\Element\RenderElement', 'processGroup'],
      ],
      '#pre_render' => [
        ['\Drupal\Core\Render\Element\Number', 'preRenderNumber'],
        ['\Drupal\Core\Render\Element\RenderElement', 'preRenderGroup'],
      ],
    ];
    if ($parents_string) {
      $form['zoom']['#group'] = $parents_string . 'general_settings';
    }

    return $form;
  }

}
