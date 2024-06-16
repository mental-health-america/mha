<?php

namespace Drupal\advanced_email_validation\Plugin\Validation\Constraint;

use Drupal\advanced_email_validation\AdvancedEmailValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use EmailValidator\EmailValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the AdvancedEmailValidation constraint.
 */
class AdvancedEmailValidationValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The Advanced email validation service.
   *
   * @var \Drupal\advanced_email_validation\AdvancedEmailValidator
   */
  protected $emailValidator;

  /**
   * Validator constructor.
   *
   * @param \Drupal\advanced_email_validation\AdvancedEmailValidator $emailValidatorWrapper
   *   The advanced_email_validation.validator service.
   */
  public function __construct(AdvancedEmailValidator $emailValidatorWrapper) {
    $this->emailValidator = $emailValidatorWrapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('advanced_email_validation.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    $email = $value->getString();
    if (empty($email)) {
      return;
    }
    $result = $this->emailValidator->validate($email);

    if ($result !== EmailValidator::NO_ERROR) {
      $errorMessage = $this->emailValidator->errorMessageFromCode($result);
      if (empty($errorMessage)) {
        $this->context->addViolation($constraint->defaultError);
      }
      $this->context->addViolation($errorMessage);
    }
  }

}
