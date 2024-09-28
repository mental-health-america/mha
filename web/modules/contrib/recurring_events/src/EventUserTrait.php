<?php

namespace Drupal\recurring_events;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * A trait for common Event User functionality.
 */
trait EventUserTrait {

  /**
   * Returns an array of base field definitions for entity owners.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to add the uid field to.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   An array of base field definitions.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   *   Thrown when the entity type does not have an "uid" entity key.
   */
  public static function ownerBaseFieldDefinitions(EntityTypeInterface $entity_type) {
    if (!$entity_type->hasKey('uid')) {
      throw new UnsupportedEntityTypeDefinitionException('The entity type ' . $entity_type->id() . ' does not have a "uid" entity key.');
    }

    return [
      $entity_type->getKey('uid') => BaseFieldDefinition::create('entity_reference')
        ->setLabel(new TranslatableMarkup('User ID'))
        ->setSetting('target_type', 'user')
        ->setTranslatable($entity_type->isTranslatable())
        ->setRevisionable($entity_type->isRevisionable())
        ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->getEntityKey('uid');
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $key = $this->getEntityType()->getKey('uid');
    $this->set($key, $uid);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    $key = $this->getEntityType()->getKey('uid');
    return $this->get($key)->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $key = $this->getEntityType()->getKey('uid');
    $this->set($key, $account);

    return $this;
  }

  /**
   * Default value callback for 'uid' base field.
   *
   * @return mixed
   *   A default value for the uid field.
   */
  public static function getDefaultEntityOwner() {
    return \Drupal::currentUser()->id();
  }

  /**
   * Backwards compatibility for getCurrentUserId().
   *
   * @return mixed
   *   A default value for the uid field.
   */
  public static function getCurrentUserId() {
    return static::getDefaultEntityOwner();
  }

}
