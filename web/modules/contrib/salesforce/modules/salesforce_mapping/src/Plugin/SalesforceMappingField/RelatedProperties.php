<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;

use Drupal\field\Entity\FieldConfig;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;

/**
 * Adapter for entity Reference and fields.
 *
 * @Plugin(
 *   id = "RelatedProperties",
 *   label = @Translation("Related Entity Properties")
 * )
 */
class RelatedProperties extends SalesforceMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    // @todo inspecting the form and form_state feels wrong, but haven't found a good way to get the entity from config before the config is saved.
    $options = $this->getConfigurationOptions($form['#entity']);

    if (empty($options)) {
      $pluginForm['drupal_field_value'] += [
        '#markup' => $this->t('No available entity reference fields.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a property from the referenced field.<br />If more than one entity is referenced, the entity at delta zero will be used.<br />An entity reference field will be used to sync an identifier, e.g. Salesforce ID and Node ID.'),
      ];
    }
    return $pluginForm;

  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    [$field_name, $referenced_field_name] = explode(':', $this->config('drupal_field_value'), 2);
    // Since we're not setting hard restrictions around bundles/fields, we may
    // have a field that doesn't exist for the given bundle/entity. In that
    // case, calling get() on an entity with a non-existent field argument
    // causes an exception during entity save. Probably a bug, but I haven't
    // found it in the issue queue. So, just check first to make sure the field
    // exists.
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );
    if (empty($instances[$field_name])) {
      return;
    }

    $field = $entity->get($field_name);
    if (empty($field->entity)) {
      // This reference field is blank.
      return;
    }

    try {
      $describe = $this
        ->salesforceClient
        ->objectDescribe($mapping->getSalesforceObjectType());
      $field_definition = $describe->getField($this->config('salesforce_field'));
      if ($field_definition['type'] == 'multipicklist') {
        $values = [];
        foreach ($field as $ref_entity) {
          if (!$ref_entity->entity->get($referenced_field_name)->isEmpty()) {
            $values[] = $ref_entity->entity->get($referenced_field_name)->value;
          }
        }
        return implode(';', $values);
      }
      else {
        return $field->entity->get($referenced_field_name)->value;
      }
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $definition = parent::getPluginDefinition();
    $definition['config_dependencies']['config'] = [];

    $field_name = $this->config('drupal_field_value');
    if ($field_name === NULL) {
      // No need to load the field if the plugin isn't configured.
      return $definition;
    }

    if (strpos($field_name, ':')) {
      [$field_name] = explode(':', $field_name, 2);
    }
    // Add reference field.
    if ($field = FieldConfig::loadByName($this->mapping->getDrupalEntityType(), $this->mapping->getDrupalBundle(), $field_name)) {
      $definition['config_dependencies']['config'][] = $field->getConfigDependencyName();
      // Add dependencies of referenced field.
      foreach ($field->getDependencies() as $type => $dependency) {
        foreach ($dependency as $item) {
          $definition['config_dependencies'][$type][] = $item;
        }
      }
    }
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function checkFieldMappingDependency(array $dependencies) {
    $definition = $this->getPluginDefinition();
    foreach ($definition['config_dependencies'] as $type => $dependency) {
      foreach ($dependency as $item) {
        if (!empty($dependencies[$type][$item])) {
          return TRUE;
        }
      }
    }
    return parent::checkFieldMappingDependency($dependencies);
  }

  /**
   * Form options helper.
   */
  protected function getConfigurationOptions($mapping) {
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );
    if (empty($instances)) {
      return;
    }

    $options = [];

    // Loop over every field on the mapped entity. For reference fields, expose
    // all properties of the referenced entity.
    foreach ($instances as $instance) {
      if (!$this->instanceOfEntityReference($instance)) {
        continue;
      }

      $settings = $instance->getSettings();
      $entity_type_id = $settings['target_type'];
      $properties = [];

      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // Exclude non-fieldables.
      if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle => $bundle_info) {
          // If target bundles is specified, limit which bundles are visible.
          if (!empty($settings['handler_settings']['target_bundles'])
            && !in_array($bundle, $settings['handler_settings']['target_bundles'])) {
            continue;
          }
          $properties += $this
            ->entityFieldManager
            ->getFieldDefinitions($entity_type_id, $bundle);
        }
      }

      foreach ($properties as $key => $property) {
        $options[(string) $instance->getLabel()][$instance->getName() . ':' . $key] = $property->getLabel();
      }
    }

    if (empty($options)) {
      return;
    }

    // Alphabetize options for UI.
    foreach ($options as &$option_set) {
      asort($option_set);
    }
    asort($options);
    return $options;
  }

}
