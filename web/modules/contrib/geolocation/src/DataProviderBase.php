<?php

namespace Drupal\geolocation;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Utility\Token;
use Exception;
use Drupal\Core\Field\FieldItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Component\Utility\Html;

/**
 * Class DataProvider Base.
 *
 * @package Drupal\geolocation
 */
abstract class DataProviderBase extends PluginBase implements DataProviderInterface, ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  protected FieldDefinitionInterface $fieldDefinition;

  protected FieldPluginBase $viewsField;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected ModuleHandler $moduleHandler,
    protected Token $token
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): DataProviderInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
      $container->get('token')
    );
  }

  /**
   * @return array
   *   Default settings.
   */
  protected function defaultSettings(): array {
    return [];
  }

  protected function getSettings(array $settings = NULL): array {
    if (is_null($settings)) {
      $settings = $this->configuration;
    }

    return $settings + $this->defaultSettings();
  }

  public function getTokenHelp(FieldDefinitionInterface $fieldDefinition = NULL): array {
    if (empty($fieldDefinition)) {
      $fieldDefinition = $this->fieldDefinition;
    }

    $element = [];
    $element['token_items'] = [
      '#type' => 'table',
      '#prefix' => '<h4>' . $this->t('Geolocation Item Tokens') . '</h4>',
      '#header' => [$this->t('Token'), $this->t('Description')],
    ];

    foreach ($fieldDefinition->getFieldStorageDefinition()->getColumns() as $id => $column) {
      $item = [
        'token' => [
          '#plain_text' => '[geolocation_current_item:' . $id . ']',
        ],
      ];

      if (!empty($column['description'])) {
        $item['description'] = [
          '#plain_text' => $column['description'],
        ];
      }

      $element['token_items'][] = $item;
    }

    if (
      $this->moduleHandler->moduleExists('token')
      && method_exists($fieldDefinition, 'getTargetEntityTypeId')
    ) {
      // Add the token UI from the token module if present.
      $element['token_help'] = [
        '#theme' => 'token_tree_link',
        '#prefix' => '<h4>' . $this->t('Additional Tokens') . '</h4>',
        '#token_types' => [$fieldDefinition->getTargetEntityTypeId()],
        '#weight' => 100,
      ];
    }

    return $element;
  }

  public function replaceFieldItemTokens($text, FieldItemInterface $fieldItem): string {
    $token_context['geolocation_current_item'] = $fieldItem;

    $entity = NULL;
    try {
      $entity = $fieldItem->getParent()->getParent()->getValue();
    }
    catch (Exception $e) {
      $this->getLogger('geolocation')->warning($e->getMessage());
    }

    if ($entity instanceof ContentEntityInterface) {
      $token_context[$entity->getEntityTypeId()] = $entity;
    }

    $text = $this->token->replace($text, $token_context, [
      'callback' => [$this, 'fieldItemTokens'],
      'clear' => TRUE,
    ]);
    return Html::decodeEntities($text);
  }

  /**
   * Token replacement support function, callback to token replacement function.
   *
   * @param array $replacements
   *   An associative array variable containing mappings from token names to
   *   values (for use with strtr()).
   * @param array $data
   *   Current item replacements.
   * @param array $options
   *   A keyed array of settings and flags to control the token replacement
   *   process. See \Drupal\Core\Utility\Token::replace().
   */
  public function fieldItemTokens(array &$replacements, array $data, array $options): void {
    if (isset($data['geolocation_current_item'])) {

      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      $item = $data['geolocation_current_item'];

      foreach ($this->fieldDefinition->getFieldStorageDefinition()->getColumns() as $id => $column) {
        if (isset($replacements['[geolocation_current_item:' . $id . ']'])) {
          $replacements['[geolocation_current_item:' . $id . ']'] = $item->get($id)->getValue();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isViewsGeoOption(FieldPluginBase $viewsField): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPositionsFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array {
    $positions = [];

    foreach ($this->getFieldItemsFromViewsRow($row, $viewsField) as $item) {
      $positions = array_merge($this->getPositionsFromItem($item), $positions);
    }

    return $positions;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocationsFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array {
    $positions = [];

    foreach ($this->getFieldItemsFromViewsRow($row, $viewsField) as $item) {
      $positions = array_merge($this->getLocationsFromItem($item), $positions);
    }

    return $positions;
  }

  /**
   * {@inheritdoc}
   */
  public function getShapesFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array {
    $positions = [];

    foreach ($this->getFieldItemsFromViewsRow($row, $viewsField) as $item) {
      $positions = array_merge($this->getShapesFromItem($item), $positions);
    }

    return $positions;
  }

  /**
   * Get field items from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Views result row.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase|null $viewsField
   *   Views field.
   *
   * @return \Drupal\Core\Field\FieldItemList|null
   *   Field items.
   */
  protected function getFieldItemsFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): ?FieldItemList {
    if (empty($viewsField)) {
      if (empty($this->viewsField)) {
        return NULL;
      }
      $viewsField = $this->viewsField;
    }

    $entity = $viewsField->getEntity($row);

    if (empty($entity->{$viewsField->definition['field_name']})) {
      return NULL;
    }

    return $entity->{$viewsField->definition['field_name']};
  }

  public function setViewsField(FieldPluginBase $viewsField): void {
    $this->viewsField = $viewsField;
  }

  public function setFieldDefinition(FieldDefinitionInterface $fieldDefinition): void {
    $this->fieldDefinition = $fieldDefinition;
  }

  public function getPositionsFromItem(FieldItemInterface $fieldItem): array {
    return [];
  }

  public function getLocationsFromItem(FieldItemInterface $fieldItem): array {
    return [];
  }

  public function getShapesFromItem(FieldItemInterface $fieldItem): array {
    return [];
  }

  /**
   * Provide settings form.
   *
   * @param array $settings
   * @param array $parents
   *
   * @return array
   *   Form.
   */
  public function getSettingsForm(array $settings, array $parents = []): array {
    return [];
  }

}
