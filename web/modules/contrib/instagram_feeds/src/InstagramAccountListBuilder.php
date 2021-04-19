<?php

namespace Drupal\instagram_feeds;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\user\EntityOwnerInterface;

/**
 * Lists instagram_account entities.
 */
class InstagramAccountListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatterService;

  /**
   * Get the list of columns to render.
   *
   * @return array
   *   The list of columns, where keys are field names and values are headers.
   */
  protected function getColumns() {
    return [
      'iid' => $this->t('Instagram User ID'),
      'account' => $this->t('Instagram Username'),
      'cron_import_limit' => $this->t('Cron Import Limit'),
      'media_bundle' => $this->t('Media Type'),
      'status' => $this->t('Status'),
      'token_expiration' => $this->t('Token Expiry'),
      'uid' => $this->t('Author'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    foreach ($this->getColumns() as $field => $label) {
      $header[$field] = [
        'data' => $label,
        'field' => $field,
        'specifier' => $field,
      ];
    }
    $header['iid']['class'] = [RESPONSIVE_PRIORITY_LOW];
    $header['media_bundle']['class'] = [RESPONSIVE_PRIORITY_LOW];
    $header['cron_import_limit']['class'] = [RESPONSIVE_PRIORITY_LOW];
    $header['token_expiration']['sort'] = 'desc';

    return $header + parent::buildHeader();
  }

  /**
   * Gets date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter service.
   */
  protected function dateFormatter(): DateFormatterInterface {
    if (!isset($this->dateFormatterService)) {
      $this->dateFormatterService = \Drupal::service('date.formatter');
    }
    return $this->dateFormatterService;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $entity */
    $row = [];
    foreach (array_keys($this->getColumns()) as $field) {
      $value = $entity->get($field)->first()->getValue();
      $row[$field]['data']['#markup'] = reset($value);
    }
    $row['uid']['data'] = [
      '#theme' => 'username',
      '#account' => $entity->getOwner(),
    ];
    $row['account'] = $entity->toLink();
    $row['status'] = empty($row['status']) ? $this->t('Disabled') : $this->t('Enabled');
    $row['token_expiration']['data'] = $this->dateFormatter()->format($entity->getTokenExpirationTime(), 'short');

    return $row + parent::buildRow($entity);
  }

}
