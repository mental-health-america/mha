<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce\Event\SalesforceBaseEvent;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

/**
 * Salesforce Pull Enqueue Event.
 */
class SalesforcePullEnqueueEvent extends SalesforceBaseEvent {

  /**
   * The mapping responsible for this pull.
   *
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   */
  protected $mapping;

  /**
   * The SelectQueryResult object associated with this pull.
   *
   * @var \Drupal\salesforce\SelectQueryResult
   */
  protected $records;

  /**
   * Current SObject from the loop.
   *
   * @var \Drupal\salesforce\SObject
   */
  protected $record;

  /**
   * Whether to force pull for the given record.
   *
   * @var bool
   */
  protected $forcePull;

  /**
   * TRUE or FALSE to indicate if pull is allowed for this event.
   *
   * @var bool
   */
  protected $enqueueAllowed;

  /**
   * Constructor.
   *
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   Mapping.
   * @param \Drupal\salesforce\SelectQueryResult $records
   *   Records.
   * @param \Drupal\salesforce\SObject $record
   *   Record.
   * @param bool $force_pull
   *   Whether to force pull.
   */
  public function __construct(SalesforceMappingInterface $mapping, SelectQueryResult $records, SObject $record, $force_pull) {
    $this->mapping = $mapping;
    $this->records = $records;
    $this->record = $record;
    $this->forcePull = $force_pull;
    $this->enqueueAllowed = TRUE;
  }

  /**
   * Get Mapping Interface object.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   *   Mapping.
   */
  public function getMapping() {
    return $this->mapping;
  }

  /**
   * Get all records.
   *
   * @return \Drupal\salesforce\SelectQueryResult
   *   Result.
   */
  public function getRecords() {
    return $this->records;
  }

  /**
   * Returns SF Object Type.
   *
   * @return string
   *   Record type.
   */
  public function getRecordType() {
    return $this->record->type();
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce\SFID
   *   Id.
   */
  public function getRecordId() {
    return $this->record->id();
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce\SObject
   *   Object.
   */
  public function getRecord() {
    return $this->record;
  }

  /**
   * Getter.
   *
   * @return array
   *   Fields.
   */
  public function getCurrentRecordFields() {
    return $this->record->fields();
  }

  /**
   * Disallow queue item.
   */
  public function disallowEnqueue() {
    $this->enqueueAllowed = FALSE;
    return $this;
  }

  /**
   * Will return FALSE if any subscribers have called disallowPull().
   *
   * @return bool
   *   TRUE if pull is allowed, false otherwise.
   */
  public function isEnqueueAllowed() {
    return $this->enqueueAllowed;
  }

  /**
   * Getter.
   *
   * @return bool
   *   Force pull.
   */
  public function getForcePull() {
    // Legacy backwards compatibility.
    // @todo remove for 8.x-3.3
    if (property_exists($this, 'force_pull')) {
      return $this->force_pull;
    }
    return $this->forcePull;
  }

}
