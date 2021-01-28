<?php

namespace Drupal\instagram_feeds\Plugin\Action\Derivative;

use Drupal\Core\Action\Plugin\Action\Derivative\EntityActionDeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\instagram_feeds\Entity\InstagramAccountInterface;

/**
 * Provides an action deriver that finds entity types of InstagramAccountInterface.
 *
 * @see \Drupal\instagram_feeds\Plugin\Action\RefreshInstagramTokenAction
 */
class RefreshInstagramTokenActionDeriver extends EntityActionDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->entityClassImplements(InstagramAccountInterface::class);
  }

}
