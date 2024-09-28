<?php

namespace Drupal\recurring_events_registration\Plugin\ComputedField;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\recurring_events_registration\Traits\RegistrationCreationServiceTrait;

/**
 * A computed field that provides the registration count of an Event Instance.
 */
class RegistrationCount extends FieldItemList {

  use ComputedItemListTrait;
  use RegistrationCreationServiceTrait;

  /**
   * The Request stack.
   *
   * @var \Drupal\Core\Http\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    // @todo Look for a better way to inject this service.
    $this->requestStack = \Drupal::service('request_stack');
  }

  /**
   * {@inheritDoc}
   */
  protected function computeValue() {
    // When saving or editing some entities, we are not interested in
    // calculating the values for its computed fields. The resulting values of
    // these computed fields are actually useful when getting/viewing/reading
    // the entities, for example, when retrieving an entity data from a GET
    // request to a REST or JSON:API endpoint.
    // If the request has the 'POST' method, assign an empty value to the
    // computed field and return.
    $current_request = $this->requestStack->getCurrentRequest();
    $route_name = $current_request->attributes->get('_route');

    // Exclude 'entity.eventseries.add_instance_form': When a new Event
    // Instance is being created via the "Add instance" option from the Series,
    // we do not want the computation to be done during the POST request.
    // @see https://www.drupal.org/project/recurring_events/issues/3391389
    $excluded_routes = [
      'entity.eventseries.add_instance_form',
    ];

    if ($current_request->getMethod() == 'POST' && in_array($route_name, $excluded_routes)) {
      $this->list[0] = $this->createItem(0, 0);
      return;
    }

    /*
     * The ComputedItemListTrait only calls this once on the same instance; from
     * then on, the value is automatically cached in $this->items, for use by
     * methods like getValue().
     */
    if (!isset($this->list[0])) {
      $entity = $this->getEntity();
      $this->list[0] = $this->createItem(0, $this->getRegistrationCreationService($entity)->retrieveRegisteredPartiesCount(TRUE, FALSE));
    }
  }

}
