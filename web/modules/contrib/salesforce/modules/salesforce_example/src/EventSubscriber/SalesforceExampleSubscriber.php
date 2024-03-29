<?php

// phpcs:ignorefile

namespace Drupal\salesforce_example\EventSubscriber;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Event\SalesforcePullEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushAllowedEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushOpEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class SalesforceExampleSubscriber.
 *
 * Trivial example of subscribing to salesforce.push_params event to set a
 * constant value for Contact.FirstName.
 *
 * @package Drupal\salesforce_example
 */
class SalesforceExampleSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Salesforce REST client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $client;

  /**
   * Create a new Salesforce object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\salesforce\Rest\RestClientInterface $salesforce_client
   *   The factory for configuration objects.
   */
  public function __construct(LoggerInterface $logger, RestClientInterface $salesforce_client) {
    $this->logger = $logger;
    $this->client = $salesforce_client;
  }

  /**
   * SalesforcePushAllowedEvent callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushAllowedEvent $event
   *   The push allowed event.
   */
  public function pushAllowed(SalesforcePushAllowedEvent $event) {
    /** @var \Drupal\Core\Entity\Entity $entity */
    $entity = $event->getEntity();
    if ($entity && $entity->getEntityTypeId() == 'unpushable_entity') {
      $event->disallowPush();
    }
  }

  /**
   * SalesforcePushParamsEvent callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent $event
   *   The event.
   */
  public function pushParamsAlter(SalesforcePushParamsEvent $event) {
    $mapping = $event->getMapping();
    $mapped_object = $event->getMappedObject();
    $params = $event->getParams();

    /** @var \Drupal\Core\Entity\Entity $entity */
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() != 'user') {
      return;
    }
    if ($mapping->id() != 'salesforce_example_contact') {
      return;
    }
    if ($mapped_object->isNew()) {
      return;
    }
    $params->setParam('FirstName', 'SalesforceExample');
  }

  /**
   * SalesforcePushParamsEvent push success callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent $event
   *   The event.
   */
  public function pushSuccess(SalesforcePushParamsEvent $event) {
    switch ($event->getMappedObject()->getMapping()->id()) {
      case 'mapping1':
        // Do X.
        break;

      case 'mapping2':
        // Do Y.
        break;
    }
    $this->messenger->addStatus('push success example subscriber!: ' . $event->getMappedObject()->sfid());
  }

  /**
   * SalesforcePushParamsEvent push fail callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushOpEvent $event
   *   The event.
   */
  public function pushFail(SalesforcePushOpEvent $event) {
    $this->messenger->addStatus('push fail example: ' . $event->getMappedObject()->id());
  }

  /**
   * SalesforceQueryEvent pull query alter event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforceQueryEvent $event
   *   The event.
   */
  public function pullQueryAlter(SalesforceQueryEvent $event) {
    $mapping = $event->getMapping();
    switch ($mapping->id()) {
      case 'contact':
        // Add attachments to the Contact pull mapping so that we can save
        // profile pics. See also ::pullPresave.
        $query = $event->getQuery()->accessCheck(FALSE);
        // Add a subquery:
        $query->fields[] = "(SELECT Id FROM Attachments WHERE Name = 'example.jpg' LIMIT 1)";
        // Add a field from lookup:
        $query->fields[] = "Account.Name";
        // Add a condition:
        $query->addCondition('Email', "''", '!=');
        // Add a limit:
        $query->limit = 5;
        break;
    }
  }

  /**
   * Pull presave event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   */
  public function pullPresave(SalesforcePullEvent $event) {
    $mapping = $event->getMapping();
    switch ($mapping->id()) {
      case 'contact':
        // In this example, given a Contact record, do a just-in-time fetch for
        // Attachment data, if given.
        $account = $event->getEntity();
        $sf_data = $event->getMappedObject()->getSalesforceRecord();
        $client = $this->client;
        // Fetch the attachment URL from raw sf data.
        $attachments = [];
        try {
          $attachments = $sf_data->field('Attachments');
        }
        catch (\Exception $e) {
          // noop, fall through.
        }
        if (@$attachments['totalSize'] < 1) {
          // If Attachments field was empty, do nothing.
          return;
        }
        // If Attachments field was set, it will contain a URL from which we can
        // fetch the attached binary. We must append "body" to the retrieved URL
        // https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_sobject_blob_retrieve.htm
        $attachment_url = $attachments['records'][0]['attributes']['url'];
        $attachment_url = $client->getInstanceUrl() . $attachment_url . '/Body';

        // Fetch the attachment body, via RestClient::httpRequestRaw.
        try {
          $file_data = $client->httpRequestRaw($attachment_url);
        }
        catch (\Exception $e) {
          // Unable to fetch file data from SF.
          $this->logger('db')->error($this->t('failed to fetch attachment for user @user', ['@user' => $account->id()]));
          return;
        }

        // Fetch file destination from account settings.
        $destination = "public://user_picture/profilepic-" . $sf_data->id() . ".jpg";

        // Attach the new file id to the user entity.
        /* var \Drupal\file\FileInterface */
        if ($file =  \Drupal::service('file.repository')->writeData($file_data, $destination, FileSystemInterface::EXISTS_REPLACE)) {
          $account->user_picture->target_id = $file->id();
        }
        else {
          $this->logger('db')->error('failed to save profile pic for user ' . $account->id());
        }

        break;
    }
  }

  /**
   * PULL_PREPULL event subscriber example.
   */
  public function pullPrepull(SalesforcePullEvent $event) {
    // For the "contact" mapping, if the SF record is marked "Inactive", do not
    // pull the record and block the user account.
    $mapping = $event->getMapping();
    switch ($mapping->id()) {
      case 'contact':
        $sf_data = $event->getMappedObject()->getSalesforceRecord();
        /** @var \Drupal\user\Entity\User $account */
        $account = $event->getEntity();
        try {
          if (!$sf_data->field('Inactive__c')) {
            // If the SF record is not marked "Inactive", proceed as normal.
            return;
          }
        }
        catch (\Exception $e) {
          // Fall through if "Inactive" field was not found.
        }
        // If we got here, SF record is marked inactive. Don't pull it.
        $event->disallowPull();
        if (!$account->isNew()) {
          // If this is an update to an existing account, block the account.
          // If this is a new account, it won't be created.
          $account->block()->save();
        }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      SalesforceEvents::PUSH_ALLOWED => 'pushAllowed',
      SalesforceEvents::PUSH_PARAMS => 'pushParamsAlter',
      SalesforceEvents::PUSH_SUCCESS => 'pushSuccess',
      SalesforceEvents::PUSH_FAIL => 'pushFail',
      SalesforceEvents::PULL_PRESAVE => 'pullPresave',
      SalesforceEvents::PULL_QUERY => 'pullQueryAlter',
      SalesforceEvents::PULL_PREPULL => 'pullPrepull',
    ];
    return $events;
  }

}
