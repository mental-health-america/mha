<?php

namespace Drupal\salesforce_address\Element;

use Drupal\address\Element\Address;

/**
 * Replaces the address line(s) on an address form element with a textarea.
 *
 * Salesforce has a textarea for the Street field, this matches that in Drupal
 * so we can sync easier.
 *
 * @FormElement("address_street_as_textarea")
 */
class AddressStreetAsTextArea extends Address {

  /**
   * {@inheritdoc}
   */
  protected static function addressElements(array $element, array $value) {
    $element = Address::addressElements($element, $value);
    $element["address_line1"]["#type"] = "textarea";
    $element["address_line1"]["#rows"] = "2";
    $element["address_line2"]["#access"] = FALSE;
    return $element;
  }

}
