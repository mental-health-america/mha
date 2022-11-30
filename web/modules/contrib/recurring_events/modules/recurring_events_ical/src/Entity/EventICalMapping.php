<?php

namespace Drupal\recurring_events_ical\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\recurring_events_ical\EventICalMappingInterface;

/**
 * Defines an event iCal property mapping entity.
 *
 * @ConfigEntityType(
 *   id = "event_ical_mapping",
 *   label = @Translation("Event iCal property mapping"),
 *   handlers = {
 *     "list_builder" = "Drupal\recurring_events_ical\EventICalMappingListBuilder",
 *     "form" = {
 *       "add" = "Drupal\recurring_events_ical\Form\EventICalMappingForm",
 *       "edit" = "Drupal\recurring_events_ical\Form\EventICalMappingForm",
 *       "delete" = "Drupal\recurring_events_ical\Form\EventICalMappingDeleteForm",
 *     }
 *   },
 *   config_prefix = "event_ical_mapping",
 *   admin_permission = "administer eventinstance types",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "collection" = "/admin/structure/events/ical",
 *     "edit-form" = "/admin/structure/events/ical/{event_ical_mapping}",
 *     "delete-form" = "/admin/structure/events/ical/{event_ical_mapping}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "properties"
 *   }
 * )
 */
class EventICalMapping extends ConfigEntityBase implements EventICalMappingInterface {

  /**
   * The event iCal mapping ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The event iCal mapping label.
   *
   * @var string
   */
  protected $label;

  /**
   * The iCal property mappings.
   *
   * @var array
   */
  protected $properties = [];

  /**
   * {@inheritdoc}
   */
  public function hasProperty(string $property): bool {
    return array_key_exists($property, $this->properties);
  }

  /**
   * {@inheritdoc}
   */
  public function getProperty(string $property): ?string {
    return $this->hasProperty($property) ? $this->properties[$property] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllProperties(): array {
    return $this->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function setProperty(string $property, string $value) {
    $this->properties[$property] = $value;
  }

}
