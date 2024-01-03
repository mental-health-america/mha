<?php

namespace Drupal\geolocation;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Geocoder Base.
 *
 * @package Drupal\geolocation
 */
abstract class GeocoderBase extends PluginBase implements GeocoderInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected GeocoderCountryFormattingManager $countryFormatterManager,
    protected ModuleHandler $moduleHandler,
    protected FileSystem $fileSystem
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): GeocoderInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.geocoder_country_formatting'),
      $container->get('module_handler'),
      $container->get('file_system')
    );
  }

  /**
   * Return plugin default settings.
   *
   * @return array
   *   Default settings.
   */
  protected function getDefaultSettings(): array {
    return [
      'label' => $this->t('Address'),
      'description' => $this->t('Enter an address to be localized.'),
      'autocomplete_min_length' => 1,
    ];
  }

  /**
   * Return plugin settings.
   *
   * @return array
   *   Settings.
   */
  public function getSettings(): array {
    $settings = NestedArray::mergeDeep($this->getDefaultSettings(), $this->configuration);

    $settings['import_path'] = $this->getJavascriptModulePath();

    return $settings;
  }

  private function getJavascriptModulePath() : ?string {
    $class_name = (new ReflectionClass($this))->getShortName();

    $module_path = $this->moduleHandler->getModule($this->getPluginDefinition()['provider'])->getPath();

    if (!file_exists($this->fileSystem->realpath($module_path) . '/js/Geocoder/' . $class_name . '.js')) {
      return NULL;
    }

    return base_path() . $module_path . '/js/Geocoder/' . $class_name . '.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsForm(): array {
    $settings = $this->getSettings();

    return [
      'label' => [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => (string) $settings['label'],
        '#size' => 15,
      ],

      'description' => [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#default_value' => (string) $settings['description'],
        '#size' => 25,
      ],

      'autocomplete_min_length' => [
        '#title' => $this->t('Autocomplete minimal input length'),
        '#type' => 'number',
        '#min' => 1,
        '#step' => 1,
        '#default_value' => (int) $settings['autocomplete_min_length'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processOptionsForm(array $form_element): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRenderArray(array &$render_array, string $identifier): ?array {
    $settings = $this->getSettings();

    $render_array['geolocation_geocoder_address'] = [
      '#type' => 'search',
      '#title' => (string) $settings['label'] ?: $this->t('Address'),
      '#placeholder' => (string) $settings['label'] ?: $this->t('Address'),
      '#description' => (string) $settings['description'] ?: $this->t('Enter an address to retrieve location.'),
      '#description_display' => 'after',
      '#maxlength' => 256,
      '#size' => 25,
      '#attributes' => [
        'class' => [
          'geolocation-geocoder-address',
        ],
        'data-source-identifier' => $identifier,
      ],
      '#attached' => [
        'library' => [
          'core/drupal.autocomplete',
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Get formatted address elements from atomics.
   *
   * @param array $address_atomics
   *   Address Atomics.
   *
   * @return array
   *   Address Elements
   */
  protected function addressElements(array $address_atomics): array {
    $formatter = $this->countryFormatterManager->getCountry($address_atomics['countryCode'], $this->getPluginId());
    if (empty($formatter)) {
      return $address_atomics;
    }
    return $formatter->format($address_atomics);
  }

  /**
   * {@inheritdoc}
   */
  public function geocode(string $address): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseGeocode(float $latitude, float $longitude): ?array {
    return NULL;
  }

}
