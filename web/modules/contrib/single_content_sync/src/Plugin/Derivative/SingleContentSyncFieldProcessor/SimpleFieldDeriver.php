<?php

namespace Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Retrieves field processor plugin definitions for simple field types.
 *
 * @see \Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor\SimpleField
 */
class SimpleFieldDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $simpleFieldTypes = [
      'address',
      'address_country',
      'boolean',
      'block_field',
      'color_field_type',
      'daterange',
      'datetime',
      'decimal',
      'email',
      'float',
      'geofield',
      'geolocation',
      'integer',
      'link',
      'list_float',
      'list_integer',
      'list_string',
      'string',
      'string_long',
      'telephone',
      'text',
      'timestamp',
      'viewsreference',
      'weight',
      'yearonly',
    ];

    foreach ($simpleFieldTypes as $fieldType) {
      $this->derivatives[$fieldType] = $base_plugin_definition;
      $this->derivatives[$fieldType]['id'] = $base_plugin_definition['id'] . ':' . $fieldType;
      $this->derivatives[$fieldType]['label'] = new TranslatableMarkup('Simple field processor for @fieldType', ['@fieldType' => $fieldType]);
      $this->derivatives[$fieldType]['field_type'] = $fieldType;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
