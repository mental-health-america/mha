<?php

/**
 * @file
 * Provide views data for geolocation.module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function geolocation_geometry_field_views_data(FieldStorageConfigInterface $field_storage) {
  // Make sure views.views.inc is loaded.
  module_load_include('inc', 'views', 'views.views');

  // Get the default data from the views module.
  $data = views_field_default_views_data($field_storage);

  $args = ['@field_name' => $field_storage->getName()];

  // Loop through all of the results and set our overrides.
  foreach ($data as $table_name => $table_data) {
    $data[$table_name]['table']['entity type'] = $field_storage->getTargetEntityTypeId();
    foreach ($table_data as $field_name => $field_data) {
      // Only modify fields.
      if ($field_name == 'delta') {
        continue;
      }
      if (isset($field_data['filter'])) {
        if (substr($field_name, -9, 9) == '_geometry') {
          $data[$table_name][$field_name]['title'] = t('Geometry (@field_name)', $args);
          continue;
        }
        if (substr($field_name, -4, 4) == '_wkt') {
          $data[$table_name][$field_name]['title'] = t('WKT/Well Known Text (@field_name)', $args);
          continue;
        }
        if (substr($field_name, -8, 8) == '_geojson') {
          $data[$table_name][$field_name]['title'] = t('GeoJSON (@field_name)', $args);
          continue;
        }
        // The default filters are mostly not useful except lat/lng.
        unset($data[$table_name][$field_name]['filter']);
      }
      if (isset($field_data['argument'])) {
        // The default arguments aren't useful at all so remove them.
        unset($data[$table_name][$field_name]['argument']);
      }
      if (isset($field_data['sort'])) {
        // The default arguments aren't useful at all so remove them.
        unset($data[$table_name][$field_name]['sort']);
      }
    }
  }

  return $data;
}

/**
 * Implements hook_field_views_data_views_data_alter().
 */
function geolocation_geometry_field_views_data_views_data_alter(array &$data, \Drupal\field\FieldStorageConfigInterface $field) {
  $source_entity_storage = \Drupal::entityTypeManager()->getStorage($field->getTargetEntityTypeId());
  $source_entity_definition = \Drupal::entityTypeManager()->getDefinition($field->getTargetEntityTypeId());

  /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $source_entity_table_mapping */
  $source_entity_table_mapping = $source_entity_storage->getTableMapping();

  foreach (\Drupal::service('entity_field.manager')->getFieldMapByFieldType('geolocation') as $entity_type => $geolocation_fields) {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $geolocation_table_mapping */
    $geolocation_table_mapping = \Drupal::entityTypeManager()->getStorage($entity_type)->getTableMapping();

    foreach ($geolocation_fields as $geolocation_field_name => $geolocation_field_data) {
      $geolocation_field_table_name = $geolocation_table_mapping->getFieldTableName($geolocation_field_name);
      if (empty($data[$geolocation_field_table_name])) {
        continue;
      }

      $label = t('Relate all @source_field contained in @field_name', ['@source_field' => $geolocation_field_name, '@field_name' => $field->getName()]);

      $data[$geolocation_field_table_name][$field->getName()] = [
        'group' => t('Geolocation Geometry'),
        'title' => $label,
        'title short' => 'Contains',
        'help' => '',
        'relationship' => [
          'title' => $label,
          'label' => $label,
          'group' => t('Geolocation Geometry'),
          'help' => '',
          'id' => 'geolocation_geometry',
          'field field' => $field->getName(),
          'field' => $geolocation_field_name,
          'base' => $source_entity_table_mapping->getDataTable(),
          'base field' => $source_entity_definition->getKey('id'),
          'entity_type' => $field->getTargetEntityTypeId(),
          'field_name' => $field->getName(),
          'field table' => $source_entity_table_mapping->getDedicatedDataTableName($field),
        ],
      ];
    }
  }

}
