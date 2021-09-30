<?php

namespace Drupal\salesforce;

/**
 * Class IdentityNotFoundException extends Runtime Exception.
 *
 * Thrown when an auth provider does not have a properly initialized identity.
 */
class IdentityNotFoundException extends \RuntimeException {

}