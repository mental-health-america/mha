<?php

namespace Drupal\geolocation_geometry\Plugin\views\join;

use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginInterface;

/**
 * Geometry joins.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("geolocation_geometry")
 */
class GeolocationGeometry extends JoinPluginBase implements JoinPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
    /** @var \Drupal\Core\Database\Query\Select $select_query */
    /** @var \Drupal\views\Plugin\views\query\Sql $view_query */

    $geometry_field = $table['alias'] . '.' . $this->field . '_geometry';
    $latitude_field = $this->leftTable . '.' . $this->leftField . '_lat';
    $longitude_field = $this->leftTable . '.' . $this->leftField . '_lng';

    $condition = "ST_Contains(" . $geometry_field . ", ST_PointFromText(CONCAT('POINT(', " . $longitude_field . ", ' ', " . $latitude_field . ", ')'), 4326))";

    $select_query->addJoin($this->type, $this->table, $table['alias'], $condition);
  }

}
