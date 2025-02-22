<?php

namespace Drupal\siteimprove\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\siteimprove\Plugin\SiteimproveDomainManager;
use Drupal\siteimprove\SiteimproveUtils;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Siteimprove settings.
 *
 * @package Drupal\siteimprove\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * SiteimproveUtils var.
   *
   * @var \Drupal\siteimprove\SiteimproveUtils
   */
  protected $siteimprove;

  /**
   * Drupal\siteimprove\Plugin\SiteimproveDomainManager definition.
   *
   * @var \Drupal\siteimprove\Plugin\SiteimproveDomainManager
   */
  protected $pluginManagerSiteimproveDomain;

  /**
   * Drupal Core Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal built-in HTTP client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The group type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $groupTypeStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, SiteimproveUtils $siteimprove, SiteimproveDomainManager $pluginManagerSiteimproveDomain, MessengerInterface $messenger, Client $httpClient, EntityStorageInterface $vocabulary_storage, ModuleHandlerInterface $moduleHandler, ?EntityStorageInterface $groupTypeStorage) {
    parent::__construct($config_factory);

    $this->siteimprove = $siteimprove;
    $this->pluginManagerSiteimproveDomain = $pluginManagerSiteimproveDomain;
    $this->messenger = $messenger;
    $this->httpClient = $httpClient;
    $this->vocabularyStorage = $vocabulary_storage;
    $this->moduleHandler = $moduleHandler;
    $this->groupTypeStorage = $groupTypeStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('config.factory'),
      $container->get('siteimprove.utils'),
      $container->get('plugin.manager.siteimprove_domain'),
      $container->get('messenger'),
      $container->get('http_client'),
      $container->get('module_handler')->moduleExists('taxonomy') ? $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary') : NULL,
      $container->get('module_handler'),
      $container->get('module_handler')->moduleExists('group') ? $container->get('entity_type.manager')->getStorage('group_type') : NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'siteimprove.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'siteimprove_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('siteimprove.settings');

    $form['container'] = [
      '#title' => $this->t('Token'),
      '#type' => 'fieldset',
    ];

    $form['container']['token'] = [
      '#default_value' => $config->get('token'),
      '#description' => $this->t('Configure Siteimprove Plugin token.'),
      '#maxlength' => 50,
      '#prefix' => '<div id="token-wrapper">',
      '#required' => TRUE,
      '#size' => 50,
      '#suffix' => '</div>',
      '#title' => $this->t('Token'),
      '#type' => 'textfield',
    ];

    $form['container']['request_new_token'] = [
      '#ajax' => [
        'callback' => '::requestToken',
        'wrapper' => 'token-wrapper',
      ],
      '#limit_validation_errors' => [],
      '#type' => 'button',
      '#value' => $this->t('Request new token'),
    ];

    $form['plugin_experience'] = [
      '#title' => $this->t('Plugin experience'),
      '#type' => 'fieldset',
    ];

    $form['plugin_experience']['use_latest_experience'] = [
      '#default_value' => $config->get('use_latest_experience'),
      '#description' => $this->t('A new version of the plugin is now available. Please note it is a work in progress and may update over time.'),
      '#title' => $this->t('Use latest experience'),
      '#type' => 'checkbox',
    ];

    $plugins = $this->pluginManagerSiteimproveDomain->getDefinitions();
    $plugin_definitions = [];
    $options = [];
    foreach ($plugins as $plugin) {
      $options[$plugin['id']] = $plugin['label'];
      $plugin_definitions[$plugin['id']] = $plugin;
    }

    $form['domain'] = [
      '#title' => $this->t('Frontend domain'),
      '#type' => 'fieldset',
    ];

    $form['domain']['domain_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Siteimprove Domain Plugins'),
      '#description' => $this->t('Choose which Siteimprove Domain plugin to use'),
      '#options' => $options,
      '#size' => 1,
      '#default_value' => $config->get('domain_plugin_id'),
      '#weight' => '0',
    ];

    foreach ($options as $key => $option) {
      /** @var \Drupal\siteimprove\Plugin\SiteimproveDomainBase $plugin */
      $plugin_definition = $plugin_definitions[$key];
      $plugin = $this->pluginManagerSiteimproveDomain->createInstance($plugin_definition['id']);
      $plugin->buildForm($form, $form_state, $plugin_definition);
      $form[$plugin_definition['id']]['#states']['visible'] = [
        ':input[name="domain_plugin"]' => [
          'value' => $plugin_definition['id'],
        ],
      ];

      $form['domain'][$plugin_definition['id']] = [
        '#type' => 'markup',
        '#markup' => '<strong>' . $plugin_definition['label'] . '</strong><br />' . $plugin_definition['description'],
        '#prefix' => '<div name="' . $plugin_definition['id'] . '_description' . '">',
        '#suffix' => '</div>',
      ];
    }

    $form['prepublish'] = [
      '#title' => $this->t('Prepublish check'),
      '#type' => 'fieldset',
    ];

    $form['prepublish']['description'] = [
      '#markup' => "<p>" . $this->t("When this is enabled, it's possible to perform a SiteImprove prepublish check when editing content, before publishing content.") . "</p>",
    ];

    $form['prepublish']['prepublish_enabled'] = [
      '#title' => $this->t('Enable prepublish check'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('prepublish_enabled'),
    ];

    $form['prepublish']['api_username'] = [
      '#type' => 'textfield',
      '#description' => $this->t('SiteImprove API username'),
      '#default_value' => $config->get('api_username'),
      '#states' => [
        'enabled' => [
          ':input[name="prepublish_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['prepublish']['api_key'] = [
      '#type' => 'textfield',
      '#description' => $this->t('SiteImprove API key'),
      '#default_value' => $config->get('api_key'),
      '#states' => [
        'enabled' => [
          ':input[name="prepublish_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    if ($config->get('prepublish_enabled')) {
      $full_config = $this->configFactory->get('siteimprove.settings');

      // Check API if prepublish checking has been enabled.
      $url = 'https://api.siteimprove.com/v2/settings/content_checking';
      $res = $this->httpClient->request('GET', $url, [
        'auth' => [$full_config->get('api_username'), $full_config->get('api_key')],
        'headers' => [
          'Accept' => 'application/json',
        ],
        'http_errors' => FALSE,
      ]);

      $form['#attached']['library'][] = 'siteimprove/siteimprove.settings';
      $form['prepublish']['api'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Siteimprove API response'),
        '#states' => [
          'visible' => [
            ':input[name="prepublish_enabled"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
      // Only treat http status code 200 as successful.
      if ($res->getStatusCode() == 200) {
        $result = Json::decode($res->getBody());
        if (isset($result['is_ready']) && $result['is_ready']) {
          $form['prepublish']['api']['is_ready'] = [
            '#type' => 'markup',
            '#markup' => '<p><span class="prepublish-is-ready">' . $this->t('Prepublish check has been enabled') . '</span></p>',
          ];
        }
        else {
          $form['prepublish']['api']['is_ready'] = [
            '#type' => 'markup',
            '#markup' => '<p><span class="prepublish-is-not-ready">' . $this->t('Prepublish check has not been enabled yet in Siteimprove. This can take several minutes. Check back later.') . '</span></p>',
          ];

          // Enable republish feature in Siteimprove.
          $this->setRepublish($config->get('api_username'), $config->get('api_key'));
        }
        $this->messenger->deleteByType('error');
      }
      else {
        $error_message = new TranslatableMarkup('<p><span class="prepublish-is-error"><strong>@line1</strong></span></p><p>@line2</p><ul><li>@line3</li><li>@line4</li></ul></p>', [
          '@line1' => 'Sorry, it looks like there is a problem',
          '@line2' => 'It was caused by one of two issues:',
          '@line3' => 'You do not have Prepublish as part of your subscription',
          '@line4' => 'You do have Prepublish but you need to validate your API key and username in Settings',
        ]);

        // Treat all other http status codes as errors.
        // Output general error message.
        $form['prepublish']['api']['error'] = [
          '#type' => 'markup',
          '#markup' => $error_message,
        ];

        $this->messenger->addError($error_message);
      }
    }

    $form['prepublish']['content_types'] = [
      '#type' => 'fieldset',
      '#title' => 'Enabled content types',
      '#states' => [
        'visible' => [
          ':input[name="prepublish_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
    $options = node_type_get_names();
    $form['prepublish']['content_types']['enabled_content_types'] = [
      '#type' => 'checkboxes',
      '#options' => $options ?? [],
      '#default_value' => $config->get('enabled_content_types') ?? [],
      '#title' => $this->t('Select prepublish check enabled content types'),
      '#description' => $this->t('Select which content types Siteimprove Prepublish check is enabled for'),
    ];

    $form['prepublish']['taxonomies'] = [
      '#type' => 'fieldset',
      '#title' => 'Enabled taxonomies',
      '#states' => [
        'visible' => [
          ':input[name="prepublish_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $taxonomy_options = [];
    foreach ($this->vocabularyStorage->loadMultiple() as $vocabulary) {
      $taxonomy_options[$vocabulary->id()] = $vocabulary->label();
    }
    $form['prepublish']['taxonomies']['enabled_taxonomies'] = [
      '#type' => 'checkboxes',
      '#options' => $taxonomy_options ?? [],
      '#default_value' => $config->get('enabled_taxonomies') ?? [],
      '#title' => $this->t('Select prepublish check enabled taxonomies'),
      '#description' => $this->t('Select which taxonomies Siteimprove Prepublish check is enabled for'),
    ];

    if ($this->moduleHandler->moduleExists('group')) {
      $form['prepublish']['group_types'] = [
        '#type' => 'fieldset',
        '#title' => 'Enabled group types',
        '#states' => [
          'visible' => [
            ':input[name="prepublish_enabled"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
      $group_options = [];
      foreach ($this->groupTypeStorage->loadMultiple() as $groupType) {
        $group_options[$groupType->id()] = $groupType->label();
      }
      $form['prepublish']['group_types']['enabled_group_types'] = [
        '#type' => 'checkboxes',
        '#options' => $group_options,
        '#default_value' => $config->get('enabled_group_types'),
        '#title' => $this->t('Select prepublish check enabled group types'),
        '#description' => $this->t('Select which group types Siteimprove Prepublish check is enabled for'),
      ];
    }

    $form['overlay'] = [
      '#title' => $this->t('Siteimprove Overlay'),
      '#type' => 'fieldset',
    ];

    $form['overlay']['description'] = [
      '#markup' => "<p>" . $this->t('Siteimprove overlay settings.') . "</p>",
    ];

    $form['overlay']['overlay_default_collapse'] = [
      '#title' => $this->t('Collapse overlay by default'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('overlay_default_collapse'),
    ];

    $form['overlay']['overlay_cookie_secure'] = [
      '#title' => $this->t('Set "secure" attribute on overlay cookie'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('overlay_cookie_secure'),
    ];
    // Invalidate siteimprove_toolbar cache tag to ensure that the toolbar's
    // cache is properly invalidated.
    Cache::invalidateTags(['siteimprove_toolbar']);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Enable Siteimprove's Republish feature on Siteimprove server.
   *
   * @param string $username
   *   API username.
   * @param string $key
   *   API key.
   */
  protected function setRepublish($username, $key) {
    $url = 'https://api.siteimprove.com/v2/settings/content_checking';
    $this->httpClient->request('POST', $url, [
      'auth' => [$username, $key],
      'headers' => [
        'Accept' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
  }

  /**
   * Implements callback for Ajax event on token request.
   *
   * @param array $form
   *   From render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current state of form.
   *
   * @return array
   *   Token field with value filled.
   */
  public function requestToken(array &$form, FormStateInterface &$form_state) {
    // Clear existing errors to avoid showing duplicate errors.
    $this->messenger->deleteByType('error');

    // Request new token.
    if ($token = $this->siteimprove->requestToken()) {
      $form['container']['token']['#value'] = $token;
    }
    else {
      $this->messenger->addError($this->t('There was an error requesting a new token. Please try again in a few minutes.'));
    }

    $form_state->setRebuild(TRUE);
    return $form['container']['token'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $domain_plugin = $form_state->getValue('domain_plugin');
    $plugin = $this->pluginManagerSiteimproveDomain->createInstance($domain_plugin);
    $plugin->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $domain_plugin = $form_state->getValue('domain_plugin');
    $this->config('siteimprove.settings')
      ->set('token', $form_state->getValue('token'))
      ->set('use_latest_experience', $form_state->getValue('use_latest_experience'))
      ->set('domain_plugin_id', $domain_plugin)
      ->set('prepublish_enabled', $form_state->getValue('prepublish_enabled'))
      ->set('overlay_default_collapse', $form_state->getValue('overlay_default_collapse'))
      ->set('overlay_cookie_secure', $form_state->getValue('overlay_cookie_secure'))
      ->set('api_username', $form_state->getValue('api_username'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('enabled_content_types', $form_state->getValue('enabled_content_types'))
      ->set('enabled_taxonomies', $form_state->getValue('enabled_taxonomies'))
      ->set('enabled_group_types', $form_state->getValue('enabled_group_types'))
      ->save();

    $plugin = $this->pluginManagerSiteimproveDomain->createInstance($domain_plugin);
    $plugin->submitForm($form, $form_state);

  }

}
