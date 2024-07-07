<?php

namespace Drupal\recurring_events_registration\Traits;

use Drupal\recurring_events_registration\RegistrationCreationService;

/**
 * A trait for standard Registration functionality.
 *
 * @package Drupal\recurring_events_registration\Traits
 */
trait RegistrationCreationServiceTrait {

  /**
   * The registration creation service.
   *
   * @var \Drupal\recurring_events_registration\RegistrationCreationService
   */
  protected $registrationCreationService;

  /**
   * Helper to get a registration creation service given an event instance.
   *
   * @param \Drupal\recurring_events\Entity\EventInstance $entity
   *   The event instance entity.
   */
  protected function getRegistrationCreationService($entity): RegistrationCreationService {
    if (!$this->registrationCreationService) {
      $this->registrationCreationService = \Drupal::service('recurring_events_registration.creation_service');
      $this->registrationCreationService->setEventInstance($entity);
    }

    return $this->registrationCreationService;
  }

}
