<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines an interface for geolocation DataProvider plugins.
 */
interface DataProviderInterface extends PluginInspectionInterface {

  /**
   * Determine valid views option.
   *
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase $viewsField
   *   Views field definition.
   *
   * @return bool
   *   Yes or no.
   */
  public function isViewsGeoOption(FieldPluginBase $viewsField): bool;

  /**
   * Determine valid field geo option.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition.
   *
   * @return bool
   *   Yes or no.
   */
  public function isFieldGeoOption(FieldDefinitionInterface $fieldDefinition): bool;

  /**
   * Get positions from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Row.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase|null $viewsField
   *   Views field definition.
   *
   * @return array
   *   Retrieved locations.
   */
  public function getPositionsFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array;

  /**
   * Get locations from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Row.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase|null $viewsField
   *   Views field definition.
   *
   * @return array
   *   Renderable locations.
   */
  public function getLocationsFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array;

  /**
   * Get shapes from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Row.
   * @param \Drupal\views\Plugin\views\field\FieldPluginBase|null $viewsField
   *   Views field definition.
   *
   * @return array
   *   Renderable shapes.
   */
  public function getShapesFromViewsRow(ResultRow $row, FieldPluginBase $viewsField = NULL): array;

  /**
   * Get positions from field item list.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Views field definition.
   *
   * @return array
   *   Retrieved coordinates.
   */
  public function getPositionsFromItem(FieldItemInterface $fieldItem): array;

  /**
   * Get locations from field item list.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Views field definition.
   *
   * @return array
   *   Renderable locations.
   */
  public function getLocationsFromItem(FieldItemInterface $fieldItem): array;

  /**
   * Get shapes from field item list.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $fieldItem
   *   Views field definition.
   *
   * @return array
   *   Renderable shapes.
   */
  public function getShapesFromItem(FieldItemInterface $fieldItem): array;

  public function replaceFieldItemTokens(string $text, FieldItemInterface $fieldItem): string;

  public function getTokenHelp(FieldDefinitionInterface $fieldDefinition = NULL): array;

  public function getSettingsForm(array $settings, array $parents = []): array;

  public function setViewsField(FieldPluginBase $viewsField);

  public function setFieldDefinition(FieldDefinitionInterface $fieldDefinition);

}
