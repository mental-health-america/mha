<?php

namespace Drupal\geolocation;

use Drupal;
use Drupal\Core\Asset\LibraryDiscovery;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class MapFeature Base.
 *
 * @package Drupal\geolocation
 */
abstract class MapFeatureBase extends PluginBase implements MapFeatureInterface, ContainerFactoryPluginInterface {

  protected array $scripts = [];
  protected array $stylesheets = [];

  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    protected ModuleHandler $moduleHandler,
    protected FileSystem $fileSystem,
    protected Token $token,
    protected LibraryDiscovery $libraryDiscovery
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $class_name = (new ReflectionClass($this))->getShortName();

    $module_path = $this->moduleHandler->getModule($this->getPluginDefinition()['provider'])->getPath();

    if (file_exists($this->fileSystem->realpath($module_path) . '/css/MapFeature/' . $class_name . '.css')) {
      $this->stylesheets[] = base_path() . $module_path . '/css/MapFeature/' . $class_name . '.css';
    }
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): MapFeatureInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('token'),
      $container->get('library.discovery')
    );
  }

  public static function getDefaultSettings(): array {
    return [];
  }

  public function getSettings(array $settings, MapProviderInterface $mapProvider = NULL): array {
    $default_settings = $this->getDefaultSettings();
    return array_replace_recursive($default_settings, $settings);
  }

  public function getSettingsSummary(array $settings, MapProviderInterface $mapProvider = NULL): array {
    $summary = [];
    $summary[] = $this->t('%feature enabled', ['%feature' => $this->getPluginDefinition()['name']]);
    return $summary;
  }

  public function getSettingsForm(array $settings, array $parents = [], MapProviderInterface $mapProvider = NULL): array {
    $form = [];

    $form['#parents'] = $parents;

    return $form;
  }

  public function validateSettingsForm(array $values, FormStateInterface $form_state, array $parents = []) {}

  private function getJavascriptModulePath() : ?string {
    $class_name = (new ReflectionClass($this))->getShortName() ?? '';

    $module_path = $this->moduleHandler->getModule($this->getPluginDefinition()['provider'])->getPath();

    if (!file_exists($this->fileSystem->realpath($module_path) . '/js/MapFeature/' . $class_name . '.js')) {
      return NULL;
    }

    return base_path() . $module_path . '/js/MapFeature/' . $class_name . '.js';
  }

  /**
   * {@inheritdoc}
   */
  public function alterMap(array $render_array, array $feature_settings = [], array $context = [], MapProviderInterface $mapProvider = NULL): array {
    $render_array['#attached'] = BubbleableMetadata::mergeAttachments($render_array['#attached'] ?? [], [
      'drupalSettings' => [
        'geolocation' => [
          'maps' => [
            $render_array['#id'] => [
              'features' => [
                $this->getPluginId() => [
                  'import_path' => $this->getJavascriptModulePath(),
                  'settings' => $feature_settings,
                  'scripts' => $this->scripts,
                  'stylesheets' => $this->stylesheets,
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    if (Drupal::service('library.discovery')->getLibraryByName($this->getPluginDefinition()['provider'], 'mapfeature.' . $this->getPluginId())) {
      $render_array['#attached'] = BubbleableMetadata::mergeAttachments($render_array['#attached'] ?? [], [
        'library' => [
          $this->getPluginDefinition()['provider'] . '/mapfeature.' . $this->getPluginId(),
        ]
      ]);
    }

    return $render_array;
  }

}
