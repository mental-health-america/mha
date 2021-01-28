<?php

namespace Drupal\instagram_feeds;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Instagram API Trait.
 */
trait InstagramApiTrait {

  /**
   * Guzzle HTTP Client instance.
   *
   * @var \GuzzleHttp\Client
   */
  private $httpClient;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  private $token;

  /**
   * The Instagram Feeds Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Sends request to Instagram and validates response status code.
   *
   * @param string $url
   *   Url to get contents for.
   * @param bool $is_json_expected
   *   Set true, in order to decode JSON data from response.
   *
   * @return string|array
   *   Response body as string if $is_json_expected is false, or JSON decoded body.
   *
   * @throws \Exception
   */
  protected function getInstagramResponceContents($url, $is_json_expected = FALSE) {
    try {
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = $this->httpClient()->get($url);
      if ($response->getStatusCode() !== 200) {
        throw new \Exception(t('Invalid responce code @code from Instagram.', [
          '@code' => $response->getStatusCode(),
        ]));
      }
      $body = $response->getBody()->getContents();
      return $is_json_expected ? Json::decode($body) : $body;
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }
    return '';
  }

  /**
   * Returns a channel logger object.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for instagram_feeds channel.
   */
  protected function logger(): LoggerInterface {
    if (!isset($this->logger)) {
      $this->logger = \Drupal::logger('instagram_feeds');
    }
    return $this->logger;
  }

  /**
   * Returns the Guzzle HTTP Client.
   *
   * @return \GuzzleHttp\Client
   */
  protected function httpClient(): Client {
    if (!isset($this->httpClient)) {
      $this->httpClient = \Drupal::httpClient();
    }
    return $this->httpClient;
  }

  /**
   * Returns the token service.
   *
   * @return \Drupal\Core\Utility\Token
   *   The token service.
   */
  protected function token(): Token {
    if (!isset($this->token)) {
      $this->token = \Drupal::token();
    }
    return $this->token;
  }

  /**
   * Sets logger for instagram_feeds channel.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The Logger factory service.
   *
   * @return $this
   */
  protected function setLogger(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('instagram_feeds');
    return $this;
  }

  /**
   * Sets Guzzle HTTP client.
   *
   * @param \GuzzleHttp\Client $client
   *  Guzzle HTTP client.
   *
   * @return $this
   */
  protected function setHttpClient(Client $client) {
    $this->httpClient = $client;
    return $this;
  }

  /**
   * Sets the token service.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   *
   * @return $this
   */
  protected function setToken(Token $token) {
    $this->token = $token;
    return $this;
  }

}
