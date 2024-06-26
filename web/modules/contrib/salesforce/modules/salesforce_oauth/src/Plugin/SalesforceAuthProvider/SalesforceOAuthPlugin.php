<?php

namespace Drupal\salesforce_oauth\Plugin\SalesforceAuthProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Salesforce OAuth user-agent flow auth provider plugin.
 *
 * @Plugin(
 *   id = "oauth",
 *   label = @Translation("Salesforce OAuth User-Agent"),
 *   credentials_class =
 *   "\Drupal\salesforce_oauth\Consumer\SalesforceOAuthCredentials"
 * )
 */
class SalesforceOAuthPlugin extends SalesforceAuthProviderPluginBase {

  /**
   * Credentials.
   *
   * @var \Drupal\salesforce_oauth\Consumer\SalesforceOAuthCredentials
   */
  protected $credentials;

  /**
   * Temp store service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempstore;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage, ConfigFactoryInterface $configFactory, PrivateTempStoreFactory $tempstore) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $httpClient, $storage, $configFactory);
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(static::defaultConfiguration(), $configuration);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('salesforce.http_client_wrapper'),
      $container->get('salesforce.auth_token_storage'),
      $container->get('config.factory'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    $defaults = parent::defaultConfiguration();
    return array_merge($defaults, [
      'consumer_secret' => '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['consumer_key'] = [
      '#title' => $this->t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $this->getCredentials()->getConsumerKey(),
    ];

    $form['consumer_secret'] = [
      '#title' => $this->t('Salesforce consumer secret'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer secret of the Salesforce remote application.'),
      '#required' => TRUE,
      '#default_value' => $this->getCredentials()->getConsumerSecret(),
    ];

    $form['login_url'] = [
      '#title' => $this->t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getCredentials()->getLoginUrl(),
      '#description' => $this->t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // Write the config id to private temp store, so that we can use the same
    // callback URL for all OAuth applications in Salesforce.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = $this->tempstore->get('salesforce_oauth');
    $tempstore->set('config_id', $form_state->getValue('id'));

    try {
      $path = $this->getAuthorizationEndpoint();
      $query = [
        'redirect_uri' => $this->getCredentials()->getCallbackUrl(),
        'response_type' => 'code',
        'client_id' => $this->getCredentials()->getConsumerKey(),
      ];

      // Send the user along to the Salesforce OAuth login form. If successful,
      // the user will be redirected to {redirect_uri} to complete the OAuth
      // handshake, and thence to the entity listing. Upon failure, the user
      // redirect URI will send the user back to the edit form.
      $form_state->setResponse(new TrustedRedirectResponse(Url::fromUri($path . '?' . http_build_query($query))
        ->toString()));
    }
    catch (\Exception $e) {
      $form_state->setError($form, $this->t("Error during authorization: %message", ['%message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumerSecret() {
    return $this->getCredentials()->getConsumerSecret();
  }

}
