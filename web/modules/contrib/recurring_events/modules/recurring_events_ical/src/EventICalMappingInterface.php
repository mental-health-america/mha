<?php

namespace Drupal\recurring_events_ical;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for event iCal property mapping entities.
 */
interface EventICalMappingInterface extends ConfigEntityInterface {

  /**
   * Returns TRUE if a property mapping exists.
   *
   * @param string $property
   *   The iCal property.
   *
   * @return bool
   *   TRUE if a mapping exists for the property.
   */
  public function hasProperty(string $property): bool;

  /**
   * Returns the value of a property mapping.
   *
   * @param string $property
   *   The iCal property.
   *
   * @return string|null
   *   The mapped value, or NULL if not mapped.
   */
  public function getProperty(string $property): ?string;

  /**
   * Returns all mapped property values.
   *
   * @return string[]
   *   An array of property mappings as $property => $value.
   */
  public function getAllProperties(): array;

  /**
   * Sets the value of a property mapping.
   *
   * @param string $property
   *   The iCal property.
   * @param string $value
   *   The value of the property.
   */
  public function setProperty(string $property, string $value);

}
