<?php

namespace Drupal\salesforce\Rest;

/**
 * Salesforce identity response wrapper interface.
 */
interface SalesforceIdentityInterface {

  /**
   * Given API type and optional API version, return the API url.
   *
   * @param string $api_type
   *   The api type, e.g. rest, partner, meta.
   * @param string $api_version
   *   If given, replace {version} placeholder. Otherwise, return the raw URL.
   *
   * @return string
   *   The API url.
   */
  public function getUrl($api_type, $api_version = NULL);

}
