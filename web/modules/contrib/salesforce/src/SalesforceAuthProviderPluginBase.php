<?php

namespace Drupal\salesforce;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\salesforce\Rest\SalesforceIdentity;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Service\Salesforce;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared methods for auth providers.
 */
abstract class SalesforceAuthProviderPluginBase extends Salesforce implements SalesforceAuthProviderInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use MessengerTrait;

  /**
   * Credentials.
   *
   * @var \Drupal\salesforce\Consumer\SalesforceCredentials
   */
  protected $credentials;

  /**
   * Configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Token storage.
   *
   * @var \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface
   */
  protected $storage;

  /**
   * Config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Provider id, e.g. jwt, oauth.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * Plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * Instance id, e.g. "sandbox1" or "production".
   *
   * @var string
   */
  protected $id;

  /**
   * SalesforceOAuthPlugin constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   *   The oauth http client.
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $storage
   *   Auth token storage service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   *   Comment.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage, ConfigFactoryInterface $configFactory) {
    $this->id = !empty($configuration['id']) ? $configuration['id'] : NULL;
    $this->configuration = $configuration;
    $this->pluginDefinition = $plugin_definition;
    $this->pluginId = $plugin_id;
    $this->credentials = $this->getCredentials();
    $this->configFactory = $configFactory;
    parent::__construct($this->getCredentials(), $httpClient, $storage, [], new Uri($this->getCredentials()->getLoginUrl()));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(static::defaultConfiguration(), $configuration);
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('salesforce.http_client_wrapper'), $container->get('salesforce.auth_token_storage'), $container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    return [
      'consumer_key' => '',
      'login_url' => 'https://test.salesforce.com',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration($key = NULL) {
    if ($key !== NULL) {
      return !empty($this->configuration[$key]) ? $this->configuration[$key] : NULL;
    }
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValue('provider_settings'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($form_state->getResponse() instanceof TrustedRedirectResponse) {
      // If we're redirecting off-site, do not proceed with save operation.
      // We'll finish saving form input when we complete the OAuth handshake
      // from Salesforce.
      return FALSE;
    }

    // Initialize identity if token is available.
    if (!$this->hasAccessToken()) {
      return TRUE;
    }
    $token = $this->getAccessToken();
    try {
      $this->refreshIdentity($token);
    }
    catch (\Exception $e) {
      watchdog_exception('salesforce', $e);
      $this->messenger()->addError($e->getMessage());
      $form_state->disableRedirect();
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestAccessToken($code, $state = NULL) {
    $token = parent::requestAccessToken($code, $state);
    $this->refreshIdentity($token);
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function refreshAccessToken(TokenInterface $token) {
    $token = parent::refreshAccessToken($token);
    $this->refreshIdentity($token);
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function refreshIdentity(TokenInterface $token) {
    $headers = [
      'Authorization' => 'OAuth ' . $token->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = $token->getExtraParams();
    $response = $this->httpClient->retrieveResponse(new Uri($data['id']), [], $headers);
    $identity = new SalesforceIdentity($response);
    $this->storage->storeIdentity($this->service(), $identity);
    return $identity;
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentials() {
    if (empty($this->credentials) || !$this->credentials->isValid()) {
      $pluginDefinition = $this->getPluginDefinition();
      $this->credentials = $pluginDefinition['credentials_class']::create($this->configuration);
    }
    return $this->credentials;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationEndpoint() {
    return new Uri($this->getCredentials()->getLoginUrl() . static::AUTH_ENDPOINT_PATH);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenEndpoint() {
    return new Uri($this->getCredentials()->getLoginUrl() . static::AUTH_TOKEN_PATH);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken() {
    return $this->storage ? $this->storage->hasAccessToken($this->id()) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken() {
    return $this->storage->retrieveAccessToken($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function revokeAccessToken() {
    return $this->storage->clearToken($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceUrl() {
    return $this->getAccessToken()->getExtraParams()['instance_url'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getApiEndpoint($api_type = 'rest') {
    $identity = $this->getIdentity();
    if (empty($identity)) {
      throw new IdentityNotFoundException();
    }
    return $identity->getUrl($api_type, $this->getApiVersion());
  }

  /**
   * {@inheritdoc}
   */
  public function getApiVersion() {
    $version = $this->configFactory->get('salesforce.settings')->get('rest_api_version.version');
    if (empty($version) || $this->configFactory->get('salesforce.settings')->get('use_latest')) {
      return self::LATEST_API_VERSION;
    }
    return $this->configFactory->get('salesforce.settings')->get('rest_api_version.version');
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentity() {
    return $this->storage->retrieveIdentity($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function service() {
    return $this->id();
  }

  /**
   * Accessor to the storage adapter to be able to retrieve tokens.
   *
   * @return \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface
   *   The token storage.
   */
  public function getStorage() {
    return $this->storage;
  }

}
