<?php

namespace Drupal\salesforce_mapping\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a set of fields are unique for the given entity type.
 */
class UniqueFieldsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $entity_type = $entity->getEntityType();
    $id_key = $entity_type->getKey('id');

    $query = $this->entityTypeManager
      ->getStorage($entity_type->id())
      ->getQuery()
      ->accessCheck(FALSE)
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($id_key, (int) $entity->id(), '<>')
      ->range(0, 1);

    foreach ($constraint->fields as $field) {
      $field_name = $field;
      if (strpos($field_name, '.')) {
        [$field_name, $property] = explode('.', $field_name, 2);
      }
      else {
        $property = $entity->{$field}->getFieldDefinition()->getMainPropertyName();
      }
      $value = $entity->{$field_name}->{$property};
      $query->condition($field, $value);
    }
    $id = $query->execute();
    if (empty($id)) {
      return;
    }
    $id = reset($id);
    if (empty($id)) {
      return;
    }
    $entity = $this->entityTypeManager
      ->getStorage($entity_type->id())
      ->load($id);
    if (empty($entity)) {
      return;
    }
    $url = $entity->toUrl();
    $message_replacements = [
      '@entity_type' => $entity_type->getSingularLabel(),
      ':url' => $url->toString(),
      '@label' => $entity->label(),
    ];
    $this->context->addViolation($constraint->message, $message_replacements);
  }

}
