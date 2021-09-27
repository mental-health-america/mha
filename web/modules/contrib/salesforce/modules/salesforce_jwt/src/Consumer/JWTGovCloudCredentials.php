<?php

namespace Drupal\salesforce_jwt\Consumer;

/**
 * JWT Gov Cloud credentials.
 */
class JWTGovCloudCredentials extends JWTCredentials {

  /**
   * Token URL for JWT OAuth authentication.
   *
   * @var string
   */
  protected $tokenUrl;

  /**
   * {@inheritdoc}
   */
  public function __construct($consumerKey, $loginUrl, $loginUser, $keyId, $tokenUrl) {
    parent::__construct($consumerKey, $loginUrl, $loginUser, $keyId);
    $this->tokenUrl = $tokenUrl;
  }

  /**
   * Constructor helper.
   *
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\salesforce_jwt\Consumer\JWTGovCloudCredentials
   *   Credentials, valid or not.
   */
  public static function create(array $configuration) {
    return new static($configuration['consumer_key'], $configuration['login_url'], $configuration['login_user'], $configuration['encrypt_key'], $configuration['token_url']);
  }

  /**
   * Token url getter.
   *
   * @return string
   *   The token url.
   */
  public function getTokenUrl() {
    return $this->tokenUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return !empty($this->loginUser) && !empty($this->consumerId) && !empty($this->keyId) && !empty($this->tokenUrl);
  }

}
