<?php

/**
 * @file
 * Provides Token integration for Instagram Feeds.
 */

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;
use Drupal\instagram_feeds\Entity\InstagramAccountInterface;
use Drupal\user\Entity\User;

/**
 * Implements hook_token_info().
 */
function instagram_feeds_token_info() {
  $types = $tokens = [];
  $types['instagram-account'] = [
    'name' => t('Instagram Account'),
    'needs-data' => 'instagram_account',
  ];
  $tokens['instagram-account']['username'] = [
    'name' => t('Instagram Account Username'),
  ];
  $tokens['instagram-account']['id'] = [
    'name' => t('Instagram Account id'),
  ];
  $tokens['instagram-account']['author'] = [
    'name' => t('Instagram Account author'),
    'type' => 'user',
  ];

  $types['instagram-post'] = [
    'name' => t('Instagram Post'),
    'needs-data' => 'instagram_post',
  ];
  $tokens['instagram-post']['username'] = [
    'name' => t('Owner Username'),
  ];
  $tokens['instagram-post']['permalink'] = [
    'name' => t('Permalink'),
  ];
  $tokens['instagram-post']['id'] = [
    'name' => t('ID'),
  ];
  $tokens['instagram-post']['caption'] = [
    'name' => t('Caption'),
  ];
  $tokens['instagram-post']['media_type'] = [
    'name' => t('Media Type'),
  ];
  $tokens['instagram-post']['media_url'] = [
    'name' => t('Media URL'),
  ];
  $tokens['instagram-post']['thumbnail_url'] = [
    'name' => t('Thumbnail URL'),
  ];
  $tokens['instagram-post']['date'] = [
    'name' => t('Date'),
    'type' => 'date',
  ];
  $tokens['instagram-post']['tags'] = [
    'name' => t('Hashtags'),
  ];
  $tokens['instagram-post']['full_name'] = [
    'name' => t('Owner Full Name'),
  ];
  return [
    'types' => $types,
    'tokens' => $tokens,
  ];
}

/**
 * Implements hook_tokens().
 */
function instagram_feeds_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  $token_service = \Drupal::token();

  if ('instagram-account' == $type && !empty($data['instagram_account']) && $data['instagram_account'] instanceof InstagramAccountInterface) {
    $replacements += _instagram_feeds_account_tokens($token_service, $tokens, $data['instagram_account'], $options, $bubbleable_metadata);
  }

  if ('instagram-post' == $type && !empty($data['instagram_post'])) {
    $replacements += _instagram_feeds_post_tokens($token_service, $tokens, $data['instagram_post'], $options, $bubbleable_metadata);
  }
  return $replacements;
}

/**
 * Helper function to process Instagram post tokens.
 *
 * @see instagram_feeds_tokens()
 */
function _instagram_feeds_account_tokens(Token $token_service, $tokens, InstagramAccountInterface $i_account, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'username':
        $replacements[$original] = $i_account->label();
        break;

      case 'id':
        $replacements[$original] = $i_account->id();
        break;

      case 'author':
        $account = $i_account->getOwner();
        $bubbleable_metadata->addCacheableDependency($account);
        $replacements[$original] = $account->label();
        break;
    }
  }
  if ($author_tokens = $token_service->findWithPrefix($tokens, 'author')) {
    $replacements += $token_service->generate('user', $author_tokens, ['user' => $i_account->getOwner()], $options, $bubbleable_metadata);
  }
  return $replacements;
}

/**
 * Helper function to process Instagram account tokens.
 *
 * @see instagram_feeds_tokens()
 */
function _instagram_feeds_post_tokens(Token $token_service, $tokens, array $post, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  /** @var Drupal\Core\Datetime\DateFormatterInterface $date_formatter_service */
  $date_formatter_service = \Drupal::service('date.formatter');

  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'date':
        $date_format = DateFormat::load('medium');
        $bubbleable_metadata->addCacheableDependency($date_format);
        $replacements[$original] = $date_formatter_service->format($post['timestamp'], 'medium');
        break;

      case 'tags':
        $replacements[$original] = implode(', ', $post['tags']);
        break;

      default:
        if (isset($post[$name])) {
          $replacements[$original] = $post[$name];
        }
        break;
    }

  }
  if ($date_tokens = $token_service->findWithPrefix($tokens, 'date')) {
    $replacements += $token_service->generate('date', $date_tokens, ['date' => $post['timestamp']], $options, $bubbleable_metadata);
  }
  return $replacements;
}
