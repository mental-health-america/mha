<?php

namespace Drupal\content_access\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for user routes.
 */
class ContentAccessController extends ControllerBase {

  /**
   * Returns content access settings page title.
   */
  public function getContentAccessTitle() {
    $node = \Drupal::routeMatch()->getParameter('node');
    $title = $this->t('Access control for <em>@title</em>', ['@title' => $node->getTitle()]);

    return $title;
  }

}
