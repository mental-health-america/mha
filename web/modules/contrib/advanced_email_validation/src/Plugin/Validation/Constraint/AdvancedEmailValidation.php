<?php

namespace Drupal\advanced_email_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Check an email address against advanced_email_validation module rules.
 *
 * @Constraint(
 *   id = "AdvancedEmailValidation",
 *   label = @Translation("Advanced Email Validation", context = "Validation"),
 *   type = "string"
 * )
 */
class AdvancedEmailValidation extends Constraint {

  /**
   * Default error message, only used as a fallback for configuration problems.
   *
   * @var string
   */
  public $defaultError = 'Not a valid email address';

}
