<?php

namespace Drupal\salesforce\Rest;

use OAuth\Common\Http\Exception\TokenResponseException;

/**
 * Wrapper for Salesforce identity response.
 */
class SalesforceIdentity implements SalesforceIdentityInterface {

  /**
   * Decoded json response data.
   *
   * @var array
   */
  protected $data;

  /**
   * Handle the identity response from Salesforce.
   *
   * @param string $responseBody
   *   JSON identity response from Salesforce.
   *
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   *   If responseBody cannot be parsed, or contains an error.
   */
  public function __construct($responseBody) {
    $data = json_decode($responseBody, TRUE);

    if (NULL === $data || !is_array($data)) {
      throw new TokenResponseException('Unable to parse response.');
    }
    elseif (isset($data['error'])) {
      throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
    }
    $this->data = $data;
  }

  /**
   * Static creation method.
   *
   * @param array $data
   *   Data array.
   *
   * @return \Drupal\salesforce\Rest\SalesforceIdentity
   *   New identity.
   *
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   */
  public static function create(array $data) {
    return new static(json_encode($data));
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl($api_type, $api_version = NULL) {
    if (empty($this->data['urls'][$api_type])) {
      return '';
    }
    $url = $this->data['urls'][$api_type];
    return $api_version ? str_replace('{version}', $api_version, $url) : $url;
  }

}
