<?php

namespace Drupal\salesforce_address\Plugin\Field\FieldWidget;

use Drupal\address\Plugin\Field\FieldWidget\AddressDefaultWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Changes the 'address_default' widget so it uses a different type.
 *
 * @FieldWidget(
 *   id = "salesforce_ready_address",
 *   label = @Translation("Salesforce-ready Address"),
 *   field_types = {
 *     "address"
 *   },
 * )
 */
class AddressDefaultWidgetStreetAsTextArea extends AddressDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['address']['#type'] = 'address_street_as_textarea';
    return $element;
  }

}
