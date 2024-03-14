<?php

namespace Drupal\salesforce_jwt\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use OAuth\Common\Http\Uri\Uri;

/**
 * JWT Oauth plugin.
 *
 * @Plugin(
 *   id = "jwt_govcloud",
 *   label = @Translation("Salesforce JWT OAuth for GovCloud"),
 *   credentials_class = "\Drupal\salesforce_jwt\Consumer\JWTGovCloudCredentials"
 * )
 */
class SalesforceJWTGovCloudPlugin extends SalesforceJWTPlugin {

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    $defaults = parent::defaultConfiguration();
    return array_merge($defaults, [
      'token_url' => '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUrl() {
    return $this->getCredentials()->getTokenUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['token_url'] = [
      '#title' => $this->t('Token URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getCredentials()->getTokenUrl(),
      '#description' => $this->t('Enter a token URL, like https://yourcompany.my.salesforce.com'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Overrides AbstractService::requestAccessToken for jwt-bearer flow.
   *
   * This is only intended to use the token url instead of login url.
   *
   * @param string $assertion
   *   The JWT assertion.
   * @param string $state
   *   Not used.
   *
   * @return \OAuth\Common\Token\TokenInterface
   *   Access Token.
   *
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   */
  public function requestAccessToken($assertion, $state = NULL) {
    $data = [
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $assertion,
    ];
    $response = $this->httpClient->retrieveResponse(new Uri($this->getTokenUrl() . static::AUTH_TOKEN_PATH), $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
    $token = $this->parseAccessTokenResponse($response);
    $this->storage->storeAccessToken($this->service(), $token);
    return $token;
  }

}
