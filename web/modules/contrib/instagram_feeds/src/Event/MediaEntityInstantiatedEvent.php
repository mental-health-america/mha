<?php

namespace Drupal\instagram_feeds\Event;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\instagram_feeds\Entity\InstagramAccountInterface;
use Drupal\media\MediaInterface;

/**
 * En event occurs when instagram_feeds creates a new media entity.
 *
 * This event is more useful than hook_entity_presave(), because it also
 * has data received from Instagram API, so it is possible to manipulate
 * that data in order to modify media entity before it will be saved.
 */
class MediaEntityInstantiatedEvent extends InstagramFeedsEventBase {

  /**
   * Just created media entity.
   *
   * @var \Drupal\media\MediaInterface
   */
  public $mediaEntity;

  /**
   * Instagram single post data.
   *
   * @var array
   */
  public $post;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $configuration
   *   The Instagram feeds module settings.
   * @param \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account
   *   The Instagram account.
   * @param \Drupal\media\MediaInterface $media
   *   The media entity just created (not saved yet).
   * @param array $post
   *   Instagram single post data.
   */
  public function __construct(ImmutableConfig $configuration, InstagramAccountInterface $account, MediaInterface $media, array $post) {
    parent::__construct($configuration, $account);
    $this->mediaEntity = $media;
    $this->post = $post;
  }

  /**
   * {@inheritdoc}
   */
  public static function getEventName(): string {
    return 'instagram_feeds_media_entity_instantiated';
  }

}
