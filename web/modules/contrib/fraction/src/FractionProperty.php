<?php

namespace Drupal\fraction;

use Drupal\Core\TypedData\TypedData;

/**
 * A computed property for storing the fraction.
 */
class FractionProperty extends TypedData {

  /**
   * Cached Fraction object.
   *
   * @var Fraction|null
   */
  protected $fraction = NULL;

  /**
   * Implements \Drupal\Core\TypedData\TypedDataInterface::getValue().
   */
  public function getValue($langcode = NULL) {
    // If a Fraction object is already available, return it.
    if ($this->fraction !== NULL) {
      return $this->fraction;
    }

    // Load the parent item.
    $item = $this->getParent();
    // Otherwise, create a Fraction object.
    $this->fraction = new Fraction($item->numerator, $item->denominator);

    return $this->fraction;
  }

}
