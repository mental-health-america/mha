<?php

namespace Drupal\instagram_feeds;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\instagram_feeds\Entity\InstagramAccountInterface;
use Drupal\instagram_feeds\Event\InstagramPostsObtainedEvent;
use Drupal\instagram_feeds\Event\MediaEntityInstantiatedEvent;
use GuzzleHttp\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Instagram Feeds Cron Handler Service.
 *
 * Triggered inside hook_cron to import Instagram Posts.
 */
class CronHandler {

  use InstagramApiTrait;

  const SETTINGS = 'instagram_feeds.settings';

  /**
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Instance of Media storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Entity Type Manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The list of published active accounts.
   *
   * @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface[]
   */
  protected $instagramAccounts;

  /**
   * Media type source plugin field names.
   *
   * @var string[]
   */
  protected $mediaTypeSources = [];

  /**
   * The CronHandler service constructor.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Client $http_client,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EventDispatcherInterface $event_dispatcher,
    Token $token,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $config = $config_factory->get(self::SETTINGS);
    $this->config = $config;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->mediaStorage = $entity_type_manager->getStorage('media');
    $this->eventDispatcher = $event_dispatcher;
    $this->setHttpClient($http_client)->setToken($token)->setLogger($logger_factory);
  }

  /**
   * Gets active Instagram account entities.
   *
   * @return \Drupal\instagram_feeds\Entity\InstagramAccountInterface[]
   */
  protected function getInstagramAccounts() : array {
    if (!isset($this->instagramAccounts)) {
      $this->instagramAccounts = [];
      $instagram_accounts = $this->entityTypeManager->getStorage('instagram_account')->loadMultiple();
      /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account */
      foreach ($instagram_accounts as $account) {
        // Skip if entity is unpublished or token is invalid.
        if ($account->isPublished() && $account->tokenIsValid()) {
          $this->instagramAccounts[$account->id()] = $account;
        }
      }
    }
    return $this->instagramAccounts;
  }

  /**
   * Cron handler to import Instagram posts for all configured accounts.
   *
   * @return $this
   */
  public function importInstagramPosts() {
    foreach ($this->getInstagramAccounts() as $account) {
      $this->processAccount($account);
    }
    return $this;
  }

  /**
   * Gets 25 recent posts created by Instagram user.
   *
   * @param \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account
   *   Instagram account.
   *
   * @return array
   *   The list of Instagram posts starting from oldest to newest.
   *
   * @throws \Exception
   */
  protected function getMedia(InstagramAccountInterface $account) {
    try {
      $request_url = $account::INSTAGRAM_GRAPH_ENDPOINT . '/me/media?' . http_build_query([
        'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username',
        'access_token' => $account->getToken(),
      ]);
      $body = $this->getInstagramResponceContents($request_url, TRUE);
      $result = array_filter($body['data'] ?: [], [$this, 'filterPostByPermalink']);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
    // Dispatch the event.
    $event = new InstagramPostsObtainedEvent($this->config, $account, $result ?? []);
    $this->eventDispatcher->dispatch(InstagramPostsObtainedEvent::getEventName(), $event);
    return $event->posts;
  }

  /**
   * Filter callback to exclude posts without permalink.
   *
   * @param array $post
   *   Instagram Post array.
   *
   * @return bool
   *   TRUE if permalink is present in the post, FALSE otherwise.
   */
  protected function filterPostByPermalink($post) : bool {
    return (bool) $post['permalink'];
  }

  /**
   * Instagram posts import processor for the given account.
   *
   * @param \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account
   *   Instagram Feed configuration Entity.
   */
  protected function processAccount(InstagramAccountInterface $account) {
    $post_count = 0;
    $max_posts = $account->getCronLimit();
    $last_imported_timestamp = $account->getLastImportTimestamp();
    foreach ($this->getMedia($account) as $post) {
      // Stop foreach if we have already reached the limit.
      if ($post_count >= $max_posts) {
        break;
      }
      // Only save the insta post if its timestamp is after the saved last
      // import date to prevent duplicates.
      if ($post['timestamp'] > $account->getLastImportTimestamp()) {
        $this->createMediaEntity($post, $account);
        $last_imported_timestamp = $post['timestamp'];
        $post_count++;
      }
    }
    // Update the last imported date only if there were new Instagram posts imported.
    if ($post_count > 0) {
      $account->setLastImportTimestamp($last_imported_timestamp)->save();
    }
    $logger_context = ['@account' => $account->label(), '@count' => $post_count];
    $this->logger->info("@count post(s) imported from @account account.<br />\n", $logger_context);
  }

  /**
   * Creates Media entity with Instagram data.
   *
   * @param array $post
   *   Instagram post data array.
   * @param \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account
   *   Instagram Feed configuration Entity.
   */
  protected function createMediaEntity(array $post, InstagramAccountInterface $account) {
    $media_type = $account->getMediaType();
    $mapping = $this->config->get('mapping.' . $media_type);
    $entity_array = ['bundle' => $media_type];
    $entity_array[$this->getInstagramSourceField($media_type)] = $post['permalink'];

    foreach ($mapping as $entity_field_name => $post_field_name) {
      if ($entity_field_name == 'name') {
        $token_data = [
          'instagram_account' => $account,
          'instagram_post' => $post,
        ];
        $entity_array['name'] = empty($post_field_name)
          ? $account->label() . ' (' . date('m/d/Y', $post['timestamp']) . ')'
          : $this->token()->replace($post_field_name, $token_data, ['clear' => TRUE]);
        $entity_array['name'] = trim($entity_array['name']);
      }
      elseif ($post_field_name == 'tags' && $post[$post_field_name]) {
        /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
        $fields = $this->entityFieldManager->getFieldDefinitions('media', $media_type);
        $field_definition = $fields[$entity_field_name];
        $entity_array[$entity_field_name] = 'entity_reference' == $field_definition->getType()
          ? $this->getTerms($field_definition, $post['tags'])
          : $post['tags'];
      }
      elseif (!empty($post_field_name) && $post[$post_field_name]) {
        $entity_array[$entity_field_name] = $post[$post_field_name];
      }
    }

    $media_entity = $this->mediaStorage->create($entity_array);
    // Dispatch an event, so other modules can modify media entity before save.
    $event = new MediaEntityInstantiatedEvent($this->config, $account, $media_entity, $post);
    $this->eventDispatcher->dispatch(MediaEntityInstantiatedEvent::getEventName(), $event);

    $event->mediaEntity->save();
  }

  /**
   * Gets IDs of existing ones or creates new taxonomy terms based on hashtags.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The array of field definitions for the bundle, keyed by field name.
   * @param string[] $tags
   *  Instagram hash tags.
   *
   * @return array
   *   An array for entity reference field with target_id => term->id().
   */
  protected function getTerms(FieldDefinitionInterface $field_definition, $tags) {
    $result = [];
    $settings = $field_definition->getSetting('handler_settings');
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $vocabulary = $settings['auto_create_bundle'] ?: reset($settings['target_bundles']);
    $terms = $term_storage->loadByProperties([
      'name' => $tags,
      'vid' => $vocabulary,
    ]);
    $existing_tags = [];
    foreach ($terms as $term) {
      $result[]['target_id'] = $term->id();
      $existing_tags[] = $term->label();
    }
    $create_tags = $settings['auto_create'] ? array_diff($tags, $existing_tags) : [];
    foreach ($create_tags as $tag) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $term_storage->create([
        'name' => $tag,
        'vid' => $vocabulary,
      ]);
      $term->save();
      $result[]['target_id'] = $term->id();
    }
    return $result;
  }

  /**
   * Gets Media source plugin field system name.
   *
   * @param string $media_type_name
   *   The Instagram media type system name.
   *
   * @return string
   *   Field system name or NULL.
   */
  protected function getInstagramSourceField($media_type_name): string {
    if (!isset($this->mediaTypeSources[$media_type_name])) {
      /** @var \Drupal\media\MediaTypeInterface $mediaType */
      $mediaType = $this->entityTypeManager->getStorage('media_type')->load($media_type_name);
      $this->mediaTypeSources[$media_type_name] = $mediaType->getSource()->getSourceFieldDefinition($mediaType)->getName();
    }
    return $this->mediaTypeSources[$media_type_name];
  }

  /**
   * Refreshes Instagram token as per scheduled frequency.
   *
   * @return $this
   */
  public function refreshTokens() {
    $current_time = \Drupal::time()->getRequestTime();
    $frequency = $this->config->get('refresh_frequency');

    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $account */
    foreach ($this->getInstagramAccounts() as $account) {
      // Long-lived tokens are valid 60 days, so token_expiration - 60 days
      // (5184000 sec) will be the date, when token was generated/refreshed
      // last time. Continue only if frequency period has gone.
      // Expired token can no longer be regenerated.
      if ($current_time > $account->getTokenExpirationTime() - 5184000 + $frequency) {
        $account->refreshToken($this->httpClient, TRUE);
      }
    }
    return $this;
  }

}
