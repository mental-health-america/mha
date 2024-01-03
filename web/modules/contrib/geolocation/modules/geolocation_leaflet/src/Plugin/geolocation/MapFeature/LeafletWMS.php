<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\MapFeature;


use Drupal\geolocation\MapFeatureBase;
use Drupal\geolocation\MapProviderInterface;

/**
 * Provides Web Map services.
 *
 * @MapFeature(
 *   id = "leaflet_wms",
 *   name = @Translation("Web Map services"),
 *   description = @Translation("Provide single-tile/untiled/nontiled layers, shared WMS sources, and GetFeatureInfo-powered identify."),
 *   type = "leaflet",
 * )
 */
class LeafletWMS extends MapFeatureBase {

  protected array $scripts = [
    'https://cdn.jsdelivr.net/gh/heigeo/leaflet.wms@0.2.0/dist/leaflet.wms.min.js',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return array_replace_recursive(
      parent::getDefaultSettings(),
      [
        'url' => '',
        'version' => '1.1.1',
        'layers' => '',
        'styles' => '',
        'srs' => '',
        'format' => 'image/jpeg',
        'transparent' => FALSE,
        'identify' => FALSE,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $settings, array $parents = [], MapProviderInterface $mapProvider = NULL): array {
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service url'),
      '#default_value' => $settings['url'],
    ];
    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service version'),
      '#default_value' => $settings['version'],
    ];
    $form['layers'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Layers to display on map'),
      '#description' => $this->t('Value is a comma-separated list of layer names.'),
      '#default_value' => $settings['layers'],
    ];
    $form['styles'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Styles in which layers are to be rendered'),
      '#description' => $this->t('Value is a comma-separated list of style names, or empty if default styling is required. Style names may be empty in the list, to use default layer styling.'),
      '#default_value' => $settings['styles'],
    ];
    $form['srs'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Spatial Reference System'),
      '#description' => $this->t('Value is in form %srs.', ['%srs' => 'EPSG:nnn']),
      '#default_value' => $settings['srs'],
    ];
    $form['format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Format for the map output'),
      '#description' => $this->t(
        'See <a href="@url">WMS output formats</a> for supported values.',
        ['@url' => 'https://docs.geoserver.org/stable/en/user/services/wms/outputformats.html#wms-output-formats']
      ),
      '#default_value' => $settings['format'],
    ];
    $form['transparent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Transparent'),
      '#description' => $this->t('Whether the map background should be transparent.'),
      '#default_value' => $settings['transparent'],
    ];
    $form['identify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Identify'),
      '#description' => $this->t('Call the WMS GetFeatureInfo service to query a map layer and return information about the underlying features.'),
      '#default_value' => $settings['identify'],
    ];

    return $form;
  }

}
