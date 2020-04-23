<?php

namespace Drupal\geolocation_geometry\Plugin\views\relationship;

use Drupal\views\Plugin\views\relationship\EntityReverse;

/**
 * Geometry joins.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("geolocation_geometry")
 */
class GeolocationGeometry extends EntityReverse {

  /**
   * Join Manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinManager;

  /**
   * Query.
   *
   * @var \Drupal\views\Plugin\views\query\Sql
   */
  public $query;

  /**
   * Called to implement a relationship in a query.
   */
  public function query() {
    $this->ensureMyTable();

    $first = [
      'left_table' => $this->tableAlias,
      'left_field' => $this->definition['field'],
      'table' => $this->definition['field table'],
      'field' => $this->definition['field field'],
      'adjusted' => TRUE,
    ];
    if (!empty($this->options['required'])) {
      $first['type'] = 'INNER';
    }

    if (!empty($this->definition['join_extra'])) {
      $first['extra'] = $this->definition['join_extra'];
    }

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $first_join */
    $first_join = $this->joinManager->createInstance('geolocation_geometry', $first);

    $first_alias = $this->query->addTable($this->definition['field table'], $this->relationship, $first_join);

    // Second, relate the field table to the entity specified using
    // the entity id on the field table and the entity's id field.
    $second = [
      'left_table' => $first_alias,
      'left_field' => 'entity_id',
      'table' => $this->definition['base'],
      'field' => $this->definition['base field'],
      'adjusted' => TRUE,
    ];

    if (!empty($this->options['required'])) {
      $second['type'] = 'INNER';
    }

    if (!empty($this->definition['join_id'])) {
      $id = $this->definition['join_id'];
    }
    else {
      $id = 'standard';
    }

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $second_join */
    $second_join = $this->joinManager->createInstance($id, $second);
    $second_join->adjusted = TRUE;

    $alias = $this->definition['field_name'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $second_join, $this->definition['base'], $this->relationship);
  }

}
