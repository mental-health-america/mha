<?php

namespace Drupal\salesforce\Storage;

use Drupal\salesforce\Rest\SalesforceIdentityInterface;
use OAuth\Common\Storage\TokenStorageInterface;

/**
 * Add identity handling to token storage.
 *
 * @package Drupal\salesforce\Storage
 */
interface SalesforceAuthTokenStorageInterface extends TokenStorageInterface {

  /**
   * Setter for identity storage.
   *
   * @return $this
   */
  public function storeIdentity($service, SalesforceIdentityInterface $identity);

  /**
   * Return boolean indicating whether this service has an identity.
   *
   * @return bool
   *   TRUE if the service has an identity.
   */
  public function hasIdentity($service);

  /**
   * Identity for the given service.
   *
   * @return \Drupal\salesforce\Rest\SalesforceIdentityInterface
   *   Identity.
   */
  public function retrieveIdentity($service);

  /**
   * Clear identity for service.
   *
   * @return $this
   */
  public function clearIdentity($service);

}
