<?php

namespace Drupal\geolocation\Plugin\Field\FieldWidget;

use Drupal;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\geolocation\MapCenterManager;
use Drupal\geolocation\MapProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation\MapProviderManager;
use Drupal\Core\Extension\ModuleHandler;

abstract class GeolocationMapWidgetBase extends WidgetBase implements ContainerFactoryPluginInterface {

  use FieldAPIHandlerTrait;

  protected MapProviderInterface $mapProvider;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected MapCenterManager $mapCenterManager,
    protected MapProviderManager $mapProviderManager,
    protected ModuleHandler $moduleHandler
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $settings = $this->getSettings();

    if (!empty($settings['map_provider_id'])) {
      $this->mapProvider = $this->mapProviderManager->getMapProvider($settings['map_provider_id'], $settings['map_provider_settings']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.geolocation.mapcenter'),
      $container->get('plugin.manager.geolocation.mapprovider'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = [
      'hide_inputs' => FALSE,
    ];
    $settings['map_provider_id'] = '';
    if (Drupal::moduleHandler()->moduleExists('geolocation_google_maps')) {
      $settings['map_provider_id'] = 'google_maps';
    }
    elseif (Drupal::moduleHandler()->moduleExists('geolocation_leaflet')) {
      $settings['map_provider_id'] = 'leaflet';
    }
    $settings['map_provider_settings'] = [];

    $settings += parent::defaultSettings();

    $settings['centre'] = [
      'fit_bounds' => [
        'enable' => TRUE,
        'weight' => -101,
        'map_center_id' => 'fit_bounds',
        'settings' => [
          'reset_zoom' => TRUE,
        ],
      ],
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $this->settings += static::defaultSettings();

    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $element = [];

    $map_provider_options = $this->mapProviderManager->getMapProviderOptions();

    if (empty($map_provider_options)) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t("No map provider found."),
      ];
    }

    $element['hide_inputs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide field inputs in favor of map.'),
      '#default_value' => $settings['hide_inputs'],
    ];

    $element['centre'] = $this->mapCenterManager->getCenterOptionsForm((array) $settings['centre'], ['widget' => $this]);

    $element['map_provider_id'] = [
      '#type' => 'select',
      '#options' => $map_provider_options,
      '#title' => $this->t('Map Provider'),
      '#default_value' => $settings['map_provider_id'],
      '#ajax' => [
        'callback' => [
          get_class($this->mapProviderManager), 'addSettingsFormAjax',
        ],
        'wrapper' => 'map-provider-settings',
        'effect' => 'fade',
      ],
    ];

    $element['map_provider_settings'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t("No settings available."),
    ];

    $parents = [
      'fields',
      $this->fieldDefinition->getName(),
      'settings_edit_form',
      'settings',
    ];

    $user_input = $form_state->getUserInput();
    $map_provider_id = NestedArray::getValue($user_input, array_merge($parents, ['map_provider_id'])) ?? $settings['map_provider_id'] ?? key($map_provider_options);

    $map_provider_settings = NestedArray::getValue($user_input, array_merge($parents, ['map_provider_settings'])) ?? $settings['map_provider_settings'];

    if (!empty($map_provider_id)) {
      $element['map_provider_settings'] = $this->mapProviderManager
        ->createInstance($map_provider_id, $map_provider_settings)
        ->getSettingsForm(
          $map_provider_settings,
          array_merge($parents, ['map_provider_settings'])
        );
    }

    $element['map_provider_settings'] = array_replace(
      $element['map_provider_settings'],
      [
        '#prefix' => '<div id="map-provider-settings">',
        '#suffix' => '</div>',
      ]
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $settings = $this->getSettings();

    return array_replace_recursive($summary, $this->mapProvider->getSettingsSummary($settings['map_provider_settings']));
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL): array {
    $element = parent::form($items, $form, $form_state, $get_delta);

    $settings = $this->getSettings();
    $id = Html::getUniqueId('edit_' . $this->fieldDefinition->getName() . '_wrapper');

    $element['#attributes'] = array_merge_recursive(
      $element['#attributes'] ?? [],
      [
        'data-widget-type' => $this->getPluginId(),
        'id' => $id,
        'class' => [
          'geolocation-map-widget',
        ],
      ]
    );
    $element['#attached'] = BubbleableMetadata::mergeAttachments(
      $element['#attached'] ?? [],
      [
        'library' => [
          'geolocation/geolocation.widget.map',
        ],
        'drupalSettings' => [
          'geolocation' => [
            'widgetSettings' => [
              $element['#attributes']['id'] => [
                'brokerImportPath' => base_path() . $this->moduleHandler->getModule('geolocation')->getPath() . '/js/GeolocationWidgetBroker.js',
                'cardinality' => $this->fieldDefinition->getFieldStorageDefinition()->getCardinality(),
                'fieldName' => $this->fieldDefinition->getName(),
                'widgetSubscribers' => [],
              ],
            ],
          ],
        ],
      ]
    );

    $element['map'] = [
      '#type' => 'geolocation_map',
      '#weight' => -10,
      '#settings' => $settings['map_provider_settings'],
      '#id' => $id . '-map',
      '#maptype' => $settings['map_provider_id'],
      '#context' => ['widget' => $this],
      'locations' => [],
    ];

    $element['map'] = $this->mapCenterManager->alterMap($element['map'], $settings['centre']);

    if ($settings['hide_inputs'] ?? FALSE) {
      if ($element['widget']['#cardinality_multiple']) {
        if (empty($element['widget']['#attributes'])) {
          $element['widget']['#attributes'] = [];
        }

        $element['widget']['#attributes'] = array_merge_recursive(
          $element['widget']['#attributes'],
          [
            'class' => [
              'visually-hidden',
            ],
          ]
        );
      }
      else {
        if (!empty($element['widget'][0])) {
          $element['widget'][0]['#attributes'] = array_merge_recursive(
            $element['widget'][0]['#attributes'],
            [
              'class' => [
                'visually-hidden',
              ],
            ]
          );
        }
      }
    }

    return $element;
  }

  public function getMapProvider(): ?MapProviderInterface {
    return $this->mapProvider ?? NULL;
  }

}
