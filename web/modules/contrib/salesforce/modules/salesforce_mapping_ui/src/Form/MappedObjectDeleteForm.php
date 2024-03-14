<?php

namespace Drupal\salesforce_mapping_ui\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a form for deleting a salesforce_mapped_oject entity.
 *
 * @ingroup content_entity_example
 */
class MappedObjectDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EventDispatcherInterface $eventDispatcher) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this mapped object?');
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the contact list.
   */
  public function getCancelUrl() {
    return $this->getEntity()->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. Event dispatcher service sends
   * Salesforce notvie level event which logs notice.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromRoute('entity.salesforce_mapped_object.list'));

    $mapped_object = $this->getEntity();
    try {
      $mapped_entity = $mapped_object->getMappedEntity();
      if ($mapped_entity) {
        $form_state->setRedirectUrl($mapped_entity->toUrl());
      }
    }
    catch (UndefinedLinkTemplateException $e) {
    }

    $message = 'MappedObject @sfid deleted.';
    $args = ['@sfid' => $mapped_object->salesforce_id->value];
    $this->eventDispatcher->dispatch(new SalesforceNoticeEvent(NULL, $message, $args), SalesforceEvents::NOTICE);
    $mapped_object->delete();
  }

}
