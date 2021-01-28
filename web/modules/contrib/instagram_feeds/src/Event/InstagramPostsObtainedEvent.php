<?php

namespace Drupal\instagram_feeds\Event;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\instagram_feeds\Entity\InstagramAccountInterface;

/**
 * En event occurs when instagram_feeds obtained posts from Instagram API.
 */
class InstagramPostsObtainedEvent extends InstagramFeedsEventBase {

  /**
   * Instagram posts.
   *
   * @var array
   */
  public $posts;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $configuration
   *   The Instagram feeds module settings.
   * @param \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account
   *   The Instagram account.
   * @param array $posts
   *   The list of Instagram posts to modify.
   */
  public function __construct(ImmutableConfig $configuration, InstagramAccountInterface $account, array $posts) {
    parent::__construct($configuration, $account);
    $this->posts = $posts;
  }

  /**
   * {@inheritdoc}
   */
  public static function getEventName(): string {
    return 'instagram_feeds_posts_obtained';
  }
}
