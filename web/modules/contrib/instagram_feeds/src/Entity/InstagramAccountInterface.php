<?php

namespace Drupal\instagram_feeds\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Url;
use Drupal\user\EntityOwnerInterface;
use GuzzleHttp\Client;

/**
 * InstagramAccount Interface.
 */
interface InstagramAccountInterface extends ContentEntityInterface, EntityPublishedInterface, EntityOwnerInterface {

  const INSTAGRAM_GRAPH_ENDPOINT = 'https://graph.instagram.com';
  const INSTAGRAM_API_ENDPOINT = 'https://api.instagram.com';

  /**
   * Gets the Instagram long-lived token.
   *
   * @param string $client_id
   *   Instagram app (client) ID.
   * @param string $client_secret
   *   Instagram app (client) secret.
   * @param string $code
   *   Instagram auth code to regenerate the token.
   * @param bool $save
   *   Save entity or not after token has been refreshed successfully.
   *
   * @return string
   *   Instagram long-lived access token for the authorized Instagram account.
   */
  public function getToken($client_id = NULL, $client_secret = NULL, $code = NULL, $save = FALSE): string;

  /**
   * Gets the Instagram long-lived token expiration time.
   *
   * @return int
   *   Unix timestamp, when token will expire.
   */
  public function getTokenExpirationTime(): int;

  /**
   * Tries to refresh long-lived Instagram access token.
   *
   * @param \GuzzleHttp\Client $http_client
   *   Guzzle HTTP Client.
   * @param bool $save
   *   Save entity or not after token has been refreshed successfully.
   *
   * @return bool
   *   True if success, false otherwise.
   *
   * @throws \Exception
   */
  public function refreshToken(Client $http_client, bool $save = FALSE): bool;

  /**
   * Checks if Instagram access token exists and hasn't expired.
   *
   * @return bool
   *   TRUE if token is set and has not been expired, FALSE otherwise.
   */
  public function tokenIsValid(): bool;

  /**
   * Gets URL to Instagram Auth form.
   *
   * @return \Drupal\Core\Url
   *   Url to Instagram Auth form.
   */
  public function getOauthUrl($client_id): Url;

  /**
   * Gets Instagram account username.
   *
   * @param bool $save
   *   Save entity or not when username obtained from Instagram API.
   *
   * @return string
   *   Instagram username.
   */
  public function getAccountName(bool $save = FALSE): string;

  /**
   * Gets the last successfully imported Instagram post timestamp.
   *
   * @return int
   *   Unix timestamp or 0.
   */
  public function getLastImportTimestamp(): int;

  /**
   * Sets the last successfully imported Instagram post timestamp.
   *
   * @param int $timestamp
   *   Unix timestamp or 0.
   *
   * @return $this
   */
  public function setLastImportTimestamp(int $timestamp = 0);

  /**
   * Gets Cron limit value.
   *
   * @return int
   *   How many posts to import during single Cron job.
   */
  public function getCronLimit(): int;

  /**
   * Gets type of media entity to create Instagram posts.
   *
   * @return string
   *   Media entity type system name.
   */
  public function getMediaType(): string;

}
