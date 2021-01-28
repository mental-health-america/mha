<?php

namespace Drupal\instagram_feeds\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\instagram_feeds\Event\InstagramPostsObtainedEvent;
use Drupal\instagram_feeds\InstagramApiTrait;
use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber to InstagramPostsObtainedEvent event.
 */
class InstagramPostsObtainedSubscriber implements EventSubscriberInterface {

  use InstagramApiTrait;

  /**
   * Contains all Drupal versions compatible value of EXISTS_REPLACE constant.
   *
   * Added for compatibility with Drupal < 8.7.0.
   *
   * @var mixed
   */
  protected $file_exists_replace;

  /**
   * Constructs a new PageManagerRoutes.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The instance of Guzzle HTTP Client.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The Logger factory service.
   */
  public function __construct(Client $http_client, Token $token, LoggerChannelFactoryInterface $logger_factory) {
    $this->setHttpClient($http_client)->setToken($token)->setLogger($logger_factory);
    // Added for compatibility with Drupal < 8.7.0.
    $this->file_exists_replace = defined('\Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE')
      ? FileSystemInterface::EXISTS_REPLACE
      : FILE_EXISTS_REPLACE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      InstagramPostsObtainedEvent::getEventName() => [
        'onInstagramPostsObtained',
        100,
      ],
    ];
  }

  /**
   * Reacts on posts obtained event.
   *
   * @param \Drupal\instagram_feeds\Event\InstagramPostsObtainedEvent $event
   *   Instance of InstagramPostsObtainedEvent.
   */
  public function onInstagramPostsObtained(InstagramPostsObtainedEvent $event) {
    $avatar = !$event->posts ? [] : $this->getAvatarFromPostPermalink($event);

    foreach ($event->posts as &$post) {
      $post['avatar'] = $avatar;
      $post['date'] = explode('+', $post['timestamp'])[0];
      $post['timestamp'] = strtotime($post['timestamp']);
      $post['full_name'] = $avatar['alt'] ?? $event->getAccount()->label();
      // If there is no caption, this field is missed in response.
      $post['caption'] = $post['caption'] ?: '';
      $post['tags'] = $this->parseHashTags($post['caption']);
    }
    // Reverse the order of posts, so older posts start from the beginning.
    $event->posts = array_reverse($event->posts);
  }

  /**
   * Scrape the Instagram post owner avatar URL using permalink URL.
   *
   * @param \Drupal\instagram_feeds\Event\InstagramPostsObtainedEvent $event
   *   Instance of InstagramPostsObtainedEvent.
   *
   * @return string[]
   *   An array with 'target_id' and 'alt' keys to fill in file reference field.
   *
   * @throws \Exception
   */
  protected function getAvatarFromPostPermalink(InstagramPostsObtainedEvent $event) {
    $permalink_url = $event->posts[0]['permalink'];
    $account = $event->getAccount();
    $avatar_dir = $event->getConfig('avatar_uri_scheme') . '://' . $event->getConfig('avatar_directory');
    $full_name = $account->getAccountName();
    try {
      $shards = explode('window._sharedData = ', $this->getInstagramResponceContents($permalink_url));
      $insta_encoded_json = explode(';</script>', $shards[1]);
      $insta_array = Json::decode($insta_encoded_json[0]);
      $owner = $insta_array['entry_data']['PostPage'][0]['graphql']['shortcode_media']['owner'] ?? [];

      if (!empty($owner['profile_pic_url']) && $this->prepareDirectory($avatar_dir)) {
        $file_data = file_get_contents($owner['profile_pic_url']);
        $full_name = $owner['full_name'] ?? 'Instagram Avatar';
        $avatar_file_extension = pathinfo(parse_url($owner['profile_pic_url'], PHP_URL_PATH), PATHINFO_EXTENSION);
        $file_uri_destination = $avatar_dir . '/' . $account->id() . '.' . $avatar_file_extension;
        $file = file_save_data($file_data, $file_uri_destination, $this->file_exists_replace);
      }

    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }

    return !empty($file) ? ['target_id' => $file->id(), 'alt' => $full_name] : [];
  }

  /**
   * Creates directory using current Drupal version compatible method.
   *
   * @param string $directory
   *   Directory path to create.
   *
   * @return bool
   *   TRUE if directory prepared successfully, FALSE otherwise.
   */
  protected function prepareDirectory($directory) {
    if (version_compare(\Drupal::VERSION, '8.7.0') >= 0) {
      return \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    }
    else {
      return file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
    }
  }

  /**
   * Parses hash tags from string.
   *
   * @param string $caption
   *
   * @return string[]
   *   An array of Instagram hash tags.
   */
  protected function parseHashTags($caption): array {
    $tags = [];
    preg_match_all('~(#\w+)~', $caption, $tags, PREG_PATTERN_ORDER);
    return $tags[1] ?: [];
  }

}
