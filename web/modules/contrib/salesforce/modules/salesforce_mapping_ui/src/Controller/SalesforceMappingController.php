<?php

namespace Drupal\salesforce_mapping_ui\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * For now, just some dynamic route names.
 */
class SalesforceMappingController extends EntityController {

  /**
   * Provides a callback for a mapping edit page Title.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the mapping edit page, if an entity was found.
   */
  public function editTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t("%label Mapping Settings", [
        '%label' => $entity->label(),
      ]);
    }
    return $this->t('New Mapping');
  }

  /**
   * Provides a callback for a mapping field config page Title.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the mapping edit page, if an entity was found.
   */
  public function fieldsTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL) {
    if ($entity = $this->doGetEntity($route_match, $_entity)) {
      return $this->t("%label Mapping Fields", [
        '%label' => $entity->label(),
      ]);
    }
  }

}
