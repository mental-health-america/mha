<?php

namespace Drupal\geolocation\Plugin\views\style;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\geolocation\DataLayerProviderManager;
use Drupal\geolocation\MapCenterManager;
use Drupal\geolocation\MapProviderManager;
use Drupal\views\Annotation\ViewsStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Allow to display several field items on a common map.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "maps_common",
 *   title = @Translation("Geolocation CommonMap"),
 *   help = @Translation("Display geolocations on a common map."),
 *   theme = "views_view_list",
 *   display_types = {"normal"},
 * )
 */
class CommonMap extends GeolocationStyleBase {

  /**
   * Map ID.
   *
   * @var string
   */
  protected string $mapId;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $data_provider_manager,
    FileUrlGeneratorInterface $file_url_generator,
    protected MapProviderManager $mapProviderManager,
    protected MapCenterManager $mapCenterManager,
    protected DataLayerProviderManager $dataLayerProviderManager,
    protected ModuleHandler $moduleHandler
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $data_provider_manager, $file_url_generator);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): CommonMap {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.dataprovider'),
      $container->get('file_url_generator'),
      $container->get('plugin.manager.geolocation.mapprovider'),
      $container->get('plugin.manager.geolocation.mapcenter'),
      $container->get('plugin.manager.geolocation.datalayerprovider'),
      $container->get('module_handler')
    );
  }

  /**
   * Map update option handling.
   *
   * Dynamic map and client location and potentially others update the view by
   * information determined on the client site. They may want to update the
   * view result as well. So we need to provide the possible ways to do that.
   *
   * @return array
   *   The determined options.
   */
  protected function getMapUpdateOptions(): array {
    $options = [];

    foreach ($this->displayHandler->getOption('filters') as $filter_id => $filter) {
      /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter_handler */
      $filter_handler = $this->displayHandler->getHandler('filter', $filter_id);

      if (!$filter_handler->isExposed()) {
        continue;
      }

      if (!empty($filter_handler->isGeolocationCommonMapOption)) {
        $options['boundary_filter_' . $filter_id] = $this->t('Boundary Filter') . ' - ' . $filter_handler->adminLabel();
      }

    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty(): bool {
    return (bool) $this->options['even_empty'];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['even_empty'] = ['default' => '1'];

    $options['dynamic_map'] = [
      'contains' => [
        'enabled' => ['default' => 0],
        'update_handler' => ['default' => ''],
        'update_target' => ['default' => ''],
        'hide_form' => ['default' => 0],
        'views_refresh_delay' => ['default' => '1200'],
      ],
    ];
    $options['centre'] = ['default' => []];

    $options['map_provider_id'] = ['default' => ''];
    $options['map_provider_settings'] = ['default' => []];

    $options['data_layers'] = [
      'default' => [
        'geolocation_default_layer:default' => [
          'enabled' => TRUE,
          'weight' => 1,
        ],
      ],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $map_provider_options = $this->mapProviderManager->getMapProviderOptions();

    if (empty($map_provider_options)) {
      $form = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t("No map provider found."),
      ];
      return;
    }

    parent::buildOptionsForm($form, $form_state);

    $map_update_target_options = $this->getMapUpdateOptions();

    /*
     * Dynamic map handling.
     */
    if (!empty($map_update_target_options)) {
      $form['dynamic_map'] = [
        '#title' => $this->t('Dynamic Map'),
        '#type' => 'fieldset',
      ];
      $form['dynamic_map']['enabled'] = [
        '#title' => $this->t('Update view on map boundary changes. Also known as "AirBnB" style.'),
        '#type' => 'checkbox',
        '#default_value' => $this->options['dynamic_map']['enabled'],
        '#description' => $this->t("If enabled, moving the map will filter results based on current map boundary. This functionality requires an exposed boundary filter. Enabling AJAX is highly recommend for best user experience. If additional views are to be updated with the map change as well, it is highly recommended to use the view containing the map as 'parent' and the additional views as attachments."),
      ];

      $form['dynamic_map']['update_handler'] = [
        '#title' => $this->t('Dynamic map update handler'),
        '#type' => 'select',
        '#default_value' => $this->options['dynamic_map']['update_handler'],
        '#description' => $this->t("The map has to know how to feed back the update boundary data to the view."),
        '#options' => $map_update_target_options,
        '#states' => [
          'visible' => [
            ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['dynamic_map']['hide_form'] = [
        '#title' => $this->t('Hide exposed filter form element if applicable.'),
        '#type' => 'checkbox',
        '#default_value' => $this->options['dynamic_map']['hide_form'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['dynamic_map']['views_refresh_delay'] = [
        '#title' => $this->t('Minimum idle time in milliseconds required to trigger views refresh'),
        '#description' => $this->t('Once the view refresh is triggered, any further change of the map bounds will have no effect until the map update is finished. User interactions like scrolling in and out or dragging the map might trigger the map idle event, before the user is finished interacting. This setting adds a delay before the view is refreshed to allow further map interactions.'),
        '#type' => 'number',
        '#min' => 0,
        '#default_value' => $this->options['dynamic_map']['views_refresh_delay'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      if ($this->displayHandler->getPluginId() !== 'page') {
        $update_targets = [
          $this->displayHandler->display['id'] => $this->t('- This display -'),
        ];
        foreach ($this->view->displayHandlers->getInstanceIds() as $instance_id) {
          $display_instance = $this->view->displayHandlers->get($instance_id);
          if (in_array($display_instance->getPluginId(), ['page', 'block'])) {
            $update_targets[$instance_id] = $display_instance->display['display_title'];
          }
        }
        $form['dynamic_map']['update_target'] = [
          '#title' => $this->t('Dynamic map update target'),
          '#type' => 'select',
          '#default_value' => $this->options['dynamic_map']['update_target'],
          '#description' => $this->t("Targets other than page or block can only update themselves."),
          '#options' => $update_targets,
          '#states' => [
            'visible' => [
              ':input[name="style_options[dynamic_map][enabled]"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    /*
     * Centre handling.
     */
    $center_form = $this->mapCenterManager->getCenterOptionsForm((array) $this->options['centre'], ['views_style' => $this]);
    $center_form['#parents'] = ['style_options', 'centre'];
    $form['centre'] = [
      '#type' => 'details',
      '#title' => $this->t('Center options'),
      'form' => $center_form,
    ];

    /*
     * Advanced settings
     */
    $form['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
    ];

    $form['even_empty'] = [
      '#group' => 'style_options][advanced_settings',
      '#title' => $this->t('Display map when no locations are found'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['even_empty'],
    ];

    $form['map_provider_id'] = [
      '#type' => 'select',
      '#options' => $map_provider_options,
      '#title' => $this->t('Map Provider'),
      '#default_value' => $this->options['map_provider_id'],
      '#ajax' => [
        'callback' => [
          get_class($this->mapProviderManager),
          'addSettingsFormAjax',
        ],
        'wrapper' => 'map-provider-settings',
        'effect' => 'fade',
      ],
    ];

    $form['map_provider_settings'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t("No settings available."),
    ];

    $user_input = $form_state->getUserInput();
    $map_provider_id = NestedArray::getValue(
      $user_input,
      ['style_options', 'map_provider_id']
    );
    if (empty($map_provider_id)) {
      $map_provider_id = $this->options['map_provider_id'];
    }
    if (empty($map_provider_id)) {
      $map_provider_id = key($map_provider_options);
    }

    $map_provider_settings = $this->options['map_provider_settings'] ?? [];
    if (
      !empty($this->options['map_provider_id'])
      && $map_provider_id != $this->options['map_provider_id']
    ) {
      $map_provider_settings = [];
      if (!empty($form_state->getValue([
        'style_options',
        'map_provider_settings',
      ]))) {
        $form_state->setValue(['style_options', 'map_provider_settings'], []);
        $form_state->setUserInput($form_state->getValues());
      }
    }

    $map_provider = NULL;
    if (
      $map_provider_id
      && $this->mapProviderManager->hasDefinition($map_provider_id)
    ) {
      $map_provider = $this->mapProviderManager->createInstance($map_provider_id, $map_provider_settings);
    }

    if ($map_provider) {
      $form['map_provider_settings'] = $map_provider->getSettingsForm(
        $map_provider_settings,
        [
          'style_options',
          'map_provider_settings',
        ]
      );
    }

    $form['map_provider_settings'] = array_replace(
      $form['map_provider_settings'],
      [
        '#prefix' => '<div id="map-provider-settings">',
        '#suffix' => '</div>',
      ]
    );

    $form['data_layers'] = [
      '#type' => 'details',
      '#title' => $this->t('Data layers'),
      '#weight' => 1,
      'form' => $this->dataLayerProviderManager->getOptionsForm($this->options['data_layers'] ?? [], ['style_options', 'data_layers'], $map_provider),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    if (empty($this->options['map_provider_id'])) {
      return $dependencies;
    }

    $definition = $this->mapProviderManager->getDefinition($this->options['map_provider_id']);

    return array_merge_recursive($dependencies, ['module' => [$definition['provider']]]);
  }


  /**
   * {@inheritdoc}
   */
  public function render(): array {

    $render = parent::render();
    if (!$render) {
      return [];
    }

    if (!empty($this->options['dynamic_map']['enabled'])) {
      // @todo Not unique enough, but uniqueid() changes on every AJAX request.
      // For the geolocationCommonMapBehavior to work, this has to stay
      // identical.
      $this->mapId = 'map-' . $this->view->id() . '-' . $this->view->current_display;
      $this->mapId = str_replace('_', '-', $this->mapId);
    }
    else {
      $this->mapId = 'map-' . $this->view->dom_id;
    }

    $map_settings = [];
    if (!empty($this->options['map_provider_settings'])) {
      $map_settings = $this->options['map_provider_settings'];
    }

    $build = [
      '#type' => 'geolocation_map',
      '#maptype' => $this->options['map_provider_id'],
      '#id' => $this->mapId,
      '#settings' => $map_settings,
      '#context' => ['view' => $this->view],
    ];

    /*
     * Dynamic map handling.
     */
    if (!empty($this->options['dynamic_map']['enabled'])) {
      if (
        !empty($this->options['dynamic_map']['update_target'])
        && $this->view->displayHandlers->has($this->options['dynamic_map']['update_target'])
      ) {
        $update_view_display_id = $this->options['dynamic_map']['update_target'];
      }
      else {
        $update_view_display_id = $this->view->current_display;
      }

      $feature_settings = [
        'enable' => TRUE,
        'hide_form' => $this->options['dynamic_map']['hide_form'],
        'views_refresh_delay' => $this->options['dynamic_map']['views_refresh_delay'],
        'update_view_id' => $this->view->id(),
        'update_view_display_id' => $update_view_display_id,
      ];

      if (str_starts_with($this->options['dynamic_map']['update_handler'], 'boundary_filter_')) {
        $filter_id = substr($this->options['dynamic_map']['update_handler'], strlen('boundary_filter_'));
        $filters = $this->displayHandler->getOption('filters');
        $filter_options = $filters[$filter_id];
        $feature_settings += [
          'boundary_filter' => TRUE,
          'parameter_identifier' => $filter_options['expose']['identifier'],
        ];
      }

      $build['#attached'] = BubbleableMetadata::mergeAttachments($build['#attached'] ?? [], [
        'drupalSettings' => [
          'geolocation' => [
            'maps' => [
              $this->mapId => [
                'features' => [
                  'geolocation_ajax_update' => [
                    'import_path' => base_path()
                      . $this->moduleHandler->getModule('geolocation')->getPath()
                      . '/js/MapFeature/GeolocationAjaxUpdate.js',
                    'settings' => $feature_settings,
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);
    }

    $this->renderFields($this->view->result);

    /*
     * Add locations to output.
     */
    foreach ($this->view->result as $row) {
      foreach ($this->getLocationsFromRow($row) as $location) {
        $build['locations'][] = $location;
      }
    }

    $build = $this->mapCenterManager->alterMap($build, $this->options['centre'], ['views_style' => $this]);

    $build = $this->dataLayerProviderManager->alterMap($build, $this->options['data_layers'], ['views_style' => $this]);

    return $build;
  }

}
