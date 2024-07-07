<?php

declare(strict_types=1);

namespace Drupal\Tests\recurring_events_registration\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\recurring_events\Entity\EventSeries;
use Drupal\recurring_events_registration\RegistrationCreationService;

/**
 * Tests the registration creation service.
 *
 * @coversDefaultClass \Drupal\recurring_events_registration\RegistrationCreationService
 * @group recurring_events_registration
 */
class RegistrationCreationServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'datetime_range',
    'options',
    'recurring_events',
    'recurring_events_registration',
    'text',
    'user',
  ];

  /**
   * Tests the instantiation of the registration creation service.
   */
  public function testServiceInstantiation(): void {
    $service = $this->getRegistrationCreationService();
    $this->assertInstanceOf(RegistrationCreationService::class, $service);
    $this->assertEmpty($service->getEventSeries(), 'A freshly retrieved service should not have an event series.');

    // Create a new event series and store it on the service.
    $series = EventSeries::create(['type' => 'default']);
    $service->setEventSeries($series);

    // Request another instance of the service from the DI container. The event
    // series should not leak between instances.
    $newService = $this->getRegistrationCreationService();
    $this->assertEmpty($newService->getEventSeries(), 'A newly instantiated service should not have an event series.');
  }

  /**
   * Returns the registration creation service. This is the system under test.
   *
   * @return \Drupal\recurring_events_registration\RegistrationCreationService
   *   The registration creation service.
   */
  protected function getRegistrationCreationService(): RegistrationCreationService {
    return $this->container->get('recurring_events_registration.creation_service');
  }

}
