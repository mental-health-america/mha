<?php

namespace Drupal\geolocation\Plugin\views\argument;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Annotation\ViewsArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler for geolocation.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("geolocation_entity_argument")
 */
class EntityArgument extends ProximityArgument implements ContainerFactoryPluginInterface {

  /**
   * Bundle info.
   *
   * @var array
   */
  protected array $bundleInfo = [];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->bundleInfo = $entity_type_bundle_info->getAllBundleInfo();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): EntityArgument {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['geolocation_entity_argument_source'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    unset($form['description']);

    $options = [];

    foreach ($this->entityFieldManager->getFieldMapByFieldType('geolocation') as $entity_type => $fields) {
      $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type);
      foreach ($fields as $field_name => $field) {
        foreach ($field['bundles'] as $bundle) {
          $bundle_label = empty($this->bundleInfo[$entity_type][$bundle]['label']) ? $entity_type_definition->getBundleLabel() : $this->bundleInfo[$entity_type][$bundle]['label'];
          $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
          $options[$entity_type . ':' . $bundle . ':' . $field_name] = $entity_type_definition->getLabel() . ' - ' . $bundle_label . ' - ' . $field_definitions[$field_name]->getLabel();
        }
      }
    }

    $form['geolocation_entity_argument_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Geolocation Entity Argument Source'),
      '#options' => $options,
      '#weight' => -10,
      '#default_value' => $this->options['geolocation_entity_argument_source'],
      '#description' => $this->t('Format should be in the following format: <strong>"654<=5mi"</strong> (defaults to km). Alternatively, just a valid entity ID, for use as reference location in other fields.'),
    ];
  }

  /**
   * Get coordinates from entity ID.
   *
   * @param int $entity_id
   *   Entity ID.
   *
   * @return array|null
   *   Coordinates.
   */
  protected function getCoordinatesFromEntityId(int $entity_id): ?array {
    if (empty($this->options['geolocation_entity_argument_source'])) {
      return NULL;
    }

    $values = [];

    $source_parts = explode(':', $this->options['geolocation_entity_argument_source']);
    $entity_type = $source_parts[0];
    $field_name = $source_parts[2];
    if (
      empty($entity_type)
      || empty($field_name)
    ) {
      return NULL;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface|null $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (empty($entity)) {
      return NULL;
    }

    $field = $entity->get($field_name);
    if ($field->isEmpty()) {
      return NULL;
    }

    /** @var \Drupal\geolocation\Plugin\Field\FieldType\GeolocationItem $item */
    $item = $field->first();

    $values['lat'] = $item->get('lat')->getValue();
    $values['lng'] = $item->get('lng')->getValue();

    return $values;
  }

  /**
   * Processes the passed argument into an array of relevant geolocation data.
   *
   * @return array
   *   The calculated values.
   */
  public function getParsedReferenceLocation(): array {
    // Cache the vales so this only gets processed once.
    static $values;

    if (!isset($values)) {
      if (empty($this->getValue())) {
        return [];
      }

      preg_match('/^([0-9]+)([<>=]+)([0-9.]+)(.*$)/', $this->getValue(), $values);

      if (
        empty($values)
        && is_numeric($this->getValue())
      ) {
        $values = $this->getCoordinatesFromEntityId($this->getValue());
        return $values;
      }

      $values = [
        'id' => (isset($values[1]) && is_numeric($values[1])) ? intval($values[1]) : FALSE,
        'operator' => (isset($values[2]) && in_array($values[2], [
          '<>',
          '=',
          '>=',
          '<=',
          '>',
          '<',
        ])) ? $values[2] : '<=',
        'distance' => (isset($values[3])) ? floatval($values[3]) : FALSE,
        'unit' => !empty($values[4]) ? $values[4] : 'km',
      ];

      $coordinates = $this->getCoordinatesFromEntityId($values['id']);
      if (empty($coordinates)) {
        return [];
      }

      $values = array_replace($values, $coordinates);
    }
    return $values;
  }

}
