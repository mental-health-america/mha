<?php

namespace Drupal\instagram_feeds\Event;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\instagram_feeds\Entity\InstagramAccountInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * En event occurs when instagram_feeds creates a new media entity.
 *
 * This event is more useful than hook_entity_presave(), because it also
 * has data received from Instagram API, so it is possible to manipulate
 * that data in order to modify media entity before it will be saved.
 */
abstract class InstagramFeedsEventBase extends Event {

  /**
   * Instagram Feeds Config bucket.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Instagram account.
   *
   * @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account
   */
  private $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $configuration
   *   The Instagram feeds module settings.
   * @param \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account
   *   The Instagram account.
   */
  public function __construct(ImmutableConfig $configuration, InstagramAccountInterface $account) {
    $this->config = $configuration;
    $this->account = $account;
  }

  /**
   * Returns event system name for dispatcher.
   */
  abstract public static function getEventName() : string;

  /**
   * Gets Instagram Feeds setting (except client_secret).
   *
   * @param string $key
   *   Config key to obtain.
   *
   * @return mixed
   *   Config key value.
   */
  public function getConfig($key) {
    // Do not share client_secret.
    return $key !== 'client_secret' ? $this->config->get($key) : 'SECRET';
  }

  /**
   * Gets related Instagram Account.
   *
   * @return \Drupal\instagram_feeds\Entity\InstagramAccountInterface
   *   Currently processed Instagram account.
   */
  public function getAccount() : InstagramAccountInterface {
    return $this->account;
  }

}
