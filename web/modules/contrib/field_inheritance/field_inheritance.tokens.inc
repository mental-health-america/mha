<?php

/**
 * @file
 * Token callbacks for the field_inheritance module.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_token_info_alter().
 */
function field_inheritance_token_info_alter(&$info) {
  if (!\Drupal::moduleHandler()->moduleExists('token')) {
    return;
  }

  foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
    if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
      continue;
    }

    // Make sure a token type exists for this entity.
    $token_type = \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($entity_type_id);
    if (empty($token_type) || !isset($info['types'][$token_type])) {
      continue;
    }

    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
    $entity_fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
    foreach (array_keys($bundles) as $bundle) {
      // Grab inherited fields just for the current entity type and bundle.
      $inherited_field_ids = \Drupal::entityQuery('field_inheritance')
        ->condition('destinationEntityType', $entity_type_id)
        ->condition('destinationEntityBundle', $bundle)
        ->execute();

      if (!empty($inherited_field_ids)) {
        /** @var \Drupal\field_inheritance\Entity\FieldInheritanceInterface[] $inherited_fields */
        $inherited_fields = \Drupal::entityTypeManager()->getStorage('field_inheritance')->loadMultiple($inherited_field_ids);
        foreach ($inherited_fields as $inherited_field) {
          if ($inherited_field->destinationEntityType() !== $entity_type_id || $inherited_field->destinationEntityBundle() !== $bundle) {
            continue;
          }

          // If a token already exists for this field, don't overwrite it.
          $inherited_field_name = $inherited_field->idWithoutTypeAndBundle();
          if (isset($info['tokens'][$token_type][$inherited_field_name])) {
            continue;
          }

          // Do not define the token if the destination field has no properties.
          $destination_field = $entity_fields[$inherited_field->destinationField()] ?? NULL;
          if (!$destination_field instanceof FieldStorageConfigInterface || !$destination_field->getPropertyDefinitions()) {
            continue;
          }

          // Use a max cardinality of 3, like Token module does.
          $cardinality = $destination_field->getCardinality();
          $cardinality = ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $cardinality > 3) ? 3 : $cardinality;

          // Build the token definition for the computed inheritance field.
          $escaped_label = Html::escape($inherited_field->label());
          $description = t('The inherited field: @field', ['@field' => $escaped_label]);
          $field_token_name = $token_type . '-' . $inherited_field_name;
          $info['tokens'][$token_type][$inherited_field_name] = [
            'name' => $escaped_label,
            'description' => $description,
            'module' => 'token',
            'type' => $cardinality > 1 ? "list<$field_token_name>" : $field_token_name,
          ];
          $info['types'][$field_token_name] = [
            'name' => $escaped_label,
            'description' => t('@label tokens.', ['@label' => $escaped_label]),
            'needs-data' => $field_token_name,
            'nested' => TRUE,
          ];

          // Show a different token for each field delta.
          if ($cardinality > 1) {
            $info['types']["list<$field_token_name>"] = [
              'name' => t('List of @type values', ['@type' => $escaped_label]),
              'description' => t('Tokens for lists of @type values.', ['@type' => $escaped_label]),
              'needs-data' => "list<$field_token_name>",
              'nested' => TRUE,
            ];
            for ($delta = 0; $delta < $cardinality; $delta++) {
              $info['tokens']["list<$field_token_name>"][$delta] = [
                'name' => t('@type type with delta @delta', [
                  '@type' => $escaped_label,
                  '@delta' => $delta,
                ]),
                'module' => 'token',
                'type' => $field_token_name,
              ];
            }
          }

          // The rest of this function is copy-paste from the latter part of the
          // Token module's field_token_info_alter() to set the same property
          // tokens on the inherited field as would be found on the destination.
          foreach ($destination_field->getPropertyDefinitions() as $property => $property_definition) {
            if (is_subclass_of($property_definition->getClass(), 'Drupal\Core\TypedData\PrimitiveInterface')) {
              $info['tokens'][$field_token_name][$property] = [
                'name' => $property_definition->getLabel(),
                'description' => $property_definition->getDescription(),
                'module' => 'token',
              ];
            }
            elseif (($property_definition instanceof DataReferenceDefinitionInterface) && ($property_definition->getTargetDefinition() instanceof EntityDataDefinitionInterface)) {
              $target_entity_type = $property_definition->getTargetDefinition()->getEntityTypeId();
              $target_token_type = \Drupal::service('token.entity_mapper')->getTokenTypeForEntityType($target_entity_type);
              $info['tokens'][$field_token_name][$property] = [
                'name' => $property_definition->getLabel(),
                'description' => $property_definition->getDescription(),
                'module' => 'token',
                'type' => $target_token_type,
              ];
            }
          }

          // Provide image_with_image_style tokens for image fields.
          if ($destination_field->getType() === 'image') {
            $image_styles = image_style_options(FALSE);
            foreach ($image_styles as $style => $description) {
              $info['tokens'][$field_token_name][$style] = [
                'name' => $description,
                'description' => t('Represents the image in the given image style.'),
                'type' => 'image_with_image_style',
              ];
            }
          }

          // Provide format token for datetime fields.
          $date_fields = ['datetime', 'timestamp', 'created', 'changed'];
          if (in_array($destination_field->getType(), $date_fields, TRUE)) {
            $info['tokens'][$field_token_name]['date'] = $info['tokens'][$field_token_name]['value'];
            $info['tokens'][$field_token_name]['date']['name'] .= ' ' . t('format');
            $info['tokens'][$field_token_name]['date']['type'] = 'date';
          }
          if ($destination_field->getType() == 'daterange' || $destination_field->getType() == 'date_recur') {
            $info['tokens'][$field_token_name]['start_date'] = $info['tokens'][$field_token_name]['value'];
            $info['tokens'][$field_token_name]['start_date']['name'] .= ' ' . t('format');
            $info['tokens'][$field_token_name]['start_date']['type'] = 'date';
            $info['tokens'][$field_token_name]['end_date'] = $info['tokens'][$field_token_name]['end_value'];
            $info['tokens'][$field_token_name]['end_date']['name'] .= ' ' . t('format');
            $info['tokens'][$field_token_name]['end_date']['type'] = 'date';
          }
        }
      }
    }
  }
}
