<?php

namespace Drupal\salesforce\Rest;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\salesforce\IdentityNotFoundException;
use Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryInterface;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
class RestClient implements RestClientInterface {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * Response object.
   *
   * @var \GuzzleHttp\Psr7\Response
   */
  public $response;

  /**
   * Used to capture the first response during an upsert.
   *
   * An upsert performs an insert or update, depending on stateful Salesforce
   * data. Unfortunately, after an insert, upsert() does not return the ID of
   * the newly created record. Therefore, our implementation of upsert performs
   * two separate requests: the first, a straight upsert() call; the second, a
   * read operation to get the id of the inserted or updated record.
   *
   * @var \GuzzleHttp\Psr7\Response|null
   */
  public $original_response;

  /**
   * GuzzleHttp client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Salesforce API URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * Salesforce immutable config object.  Useful for gets.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $immutableConfig;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The JSON serializer service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $json;

  /**
   * The Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Auth provider manager.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface
   */
  protected $authManager;

  /**
   * Active auth provider.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderInterface
   */
  protected $authProvider;

  /**
   * Active auth provider config.
   *
   * @var \Drupal\salesforce\Entity\SalesforceAuthConfig
   */
  protected $authConfig;

  /**
   * Active auth token.
   *
   * @var \OAuth\OAuth2\Token\TokenInterface
   */
  protected $authToken;

  /**
   * HTTP client options.
   *
   * @var array
   */
  protected $httpClientOptions;

  /**
   * Default HTTP headers.
   *
   * @var array
   */
  protected $defaultHeaders;

  const CACHE_LIFETIME = 300;
  const LONGTERM_CACHE_LIFETIME = 86400;

  /**
   * Constructor which initializes the consumer.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The GuzzleHttp Client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Serialization\Json $json
   *   The JSON serializer service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Time service.
   * @param \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface $authManager
   *   Auth manager service.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json, TimeInterface $time, SalesforceAuthProviderPluginManagerInterface $authManager) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->immutableConfig = $this->configFactory->get('salesforce.settings');
    $this->state = $state;
    $this->cache = $cache;
    $this->json = $json;
    $this->time = $time;
    $this->httpClientOptions = [];
    $this->authManager = $authManager;
    $this->authProvider = $authManager->getProvider();
    $this->authConfig = $authManager->getConfig();
    $this->authToken = $authManager->getToken();
    $this->defaultHeaders = [
      'Content-type' => 'application/json',
    ];
    return $this;
  }

  /**
   * Getter.
   */
  public function getShortTermCacheLifetime() {
    return $this->immutableConfig->get('short_term_cache_lifetime') ?? static::CACHE_LIFETIME;
  }

  /**
   * Getter.
   */
  public function getLongTermCacheLifetime() {
    return $this->immutableConfig->get('long_term_cache_lifetime') ?? static::LONGTERM_CACHE_LIFETIME;
  }

  /**
   * {@inheritdoc}
   */
  public function isInit() {
    if (!$this->authProvider || !$this->authManager) {
      return FALSE;
    }
    // If authToken is not set, try refreshing it before failing init.
    if (!$this->authToken) {
      try {
        $this->authToken = $this->authManager->refreshToken();
        return isset($this->authToken);
      }
      catch (\Exception $e) {
        $this->getLogger('salesforce')->error('@e', ['@e' => $e->getMessage()]);
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function apiCall($path, $params = [], $method = 'GET', $returnObject = FALSE, array $headers = []) {
    if (!$this->isInit()) {
      throw new RestException(NULL, 'RestClient is not initialized.');
    }

    if (strpos($path, '/') === 0) {
      $url = $this->authProvider->getInstanceUrl() . $path;
    }
    else {
      $url = $this->authProvider->getApiEndpoint() . $path;
    }

    try {
      $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method, $headers));
    }
    catch (RequestException $e) {
      // RequestException gets thrown for any response status but 2XX.
      $this->response = $e->getResponse();

      // Any exceptions besides 401 get bubbled up.
      if (!$this->response || $this->response->getStatusCode() != 401) {
        throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
      }
    }

    if ($this->response->getStatusCode() == 401) {
      // The session ID or OAuth token used has expired or is invalid: refresh
      // token. If refresh_token() throws an exception, or if apiHttpRequest()
      // throws anything but a RequestException, let it bubble up.
      $this->authToken = $this->authManager->refreshToken();
      try {
        $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method, $headers));
      }
      catch (RequestException $e) {
        $this->response = $e->getResponse();
        throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
      }
    }

    if (empty($this->response)
    || ((int) floor($this->response->getStatusCode() / 100)) != 2) {
      throw new RestException($this->response, 'Unknown error occurred during API call "' . $path . '": status code ' . $this->response->getStatusCode() . ' : ' . $this->response->getReasonPhrase());
    }

    $this->updateApiUsage($this->response);

    if ($returnObject) {
      return $this->response;
    }
    else {
      return $this->response->data;
    }
  }

  /**
   * Private helper to issue an SF API request.
   *
   * @param string $url
   *   Fully-qualified URL to resource.
   * @param array|string $params
   *   Parameters to provide.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   * @param array $headers
   *   The http headers to merge into the request.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Response object.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function apiHttpRequest($url, $params, $method, array $headers = []) {
    if (!$this->authToken) {
      throw new \Exception('Missing OAuth Token');
    }

    $headers['Authorization'] = 'OAuth ' . $this->authToken->getAccessToken();
    $headers = array_merge($this->defaultHeaders, $headers);

    $data = NULL;
    if (!empty($params)) {
      $data = is_array($params) ? $this->json->encode($params) : $params;
    }
    return $this->httpRequest($url, $data, $headers, $method);
  }

  /**
   * {@inheritdoc}
   */
  public function httpRequestRaw($url) {
    if (!$this->authManager->getToken()) {
      throw new \Exception('Missing OAuth Token');
    }
    $headers = [
      'Authorization' => 'OAuth ' . $this->authToken->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $response = $this->httpRequest($url, NULL, $headers);
    return $response->getBody()->getContents();
  }

  /**
   * Make the HTTP request. Wrapper around drupal_http_request().
   *
   * @param string $url
   *   Path to make request from.
   * @param string $data
   *   The request body.
   * @param array $headers
   *   Request headers to send as name => value.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Response object.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Request exception.
   */
  protected function httpRequest($url, $data = NULL, array $headers = [], $method = 'GET') {
    // Build the request, including path and headers. Internal use.
    $args = NestedArray::mergeDeep($this->httpClientOptions, ['headers' => $headers, 'body' => $data]);
    return $this->httpClient->$method($url, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpClientOptions(array $options) {
    $this->httpClientOptions = NestedArray::mergeDeep($this->httpClientOptions, $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpClientOption($option_name, $option_value) {
    $this->httpClientOptions[$option_name] = $option_value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClientOptions() {
    return $this->httpClientOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClientOption($option_name) {
    return $this->httpClientOptions[$option_name];
  }

  /**
   * Extract normalized error information from a RequestException.
   *
   * @param \GuzzleHttp\Exception\RequestException $e
   *   Exception object.
   *
   * @return array
   *   Error array with keys:
   *   * message
   *   * errorCode
   *   * fields
   */
  protected function getErrorData(RequestException $e) {
    $response = $e->getResponse();
    $response_body = $response->getBody()->getContents();
    $data = $this->json->decode($response_body);
    if (!empty($data[0])) {
      $data = $data[0];
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersions($reset = FALSE) {
    if (!$reset && ($cache = $this->cache->get('salesforce:versions'))) {
      return $cache->data;
    }

    $versions = [];
    if (!$this->authProvider) {
      return [];
    }
    try {
      $id = $this->authProvider->getIdentity();
    }
    catch (IdentityNotFoundException $e) {
      return [];
    }

    $url = str_replace('v{version}/', '', $id->getUrl('rest'));
    $response = new RestResponse($this->httpRequest($url));
    foreach ($response->data as $version) {
      $versions[$version['version']] = $version;
    }
    $this->cache->set('salesforce:versions', $versions, $this->getRequestTime() + $this->getLongTermCacheLifetime(), ['salesforce']);
    return $versions;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiUsage() {
    return $this->state->get('salesforce.usage');
  }

  /**
   * Helper to extract API Usage info from response header and write to state.
   *
   * @param \Drupal\salesforce\Rest\RestResponse $response
   *   A REST API response.
   */
  protected function updateApiUsage(RestResponse $response) {
    if ($limit_info = $response->getHeader('Sforce-Limit-Info')) {
      if (is_array($limit_info)) {
        $limit_info = reset($limit_info);
      }
      $this->state->set('salesforce.usage', $limit_info);
    }
  }

  /**
   * @defgroup salesforce_apicalls Wrapper calls around core apiCall()
   */

  /**
   * {@inheritdoc}
   */
  public function objects(array $conditions = ['updateable' => TRUE], $reset = FALSE) {
    // Use the cached data if we have it.
    if (!$reset && ($cache = $this->cache->get('salesforce:objects'))) {
      $result = $cache->data;
    }
    else {
      $result = $this->apiCall('sobjects');
      $this->cache->set('salesforce:objects', $result, $this->getRequestTime() + $this->getShortTermCacheLifetime(), ['salesforce']);
    }

    $sobjects = [];
    // Filter the list by conditions, and assign SF table names as array keys.
    foreach ($result['sobjects'] as $object) {
      if (!empty($conditions)) {
        foreach ($conditions as $condition => $value) {
          if ($object[$condition] == $value) {
            $sobjects[$object['name']] = $object;
          }
        }
      }
      else {
        $sobjects[$object['name']] = $object;
      }
    }
    return $sobjects;
  }

  /**
   * {@inheritdoc}
   */
  public function query(SelectQueryInterface $query) {
    // $this->moduleHandler->alter('salesforce_query', $query);
    // Casting $query as a string calls SelectQuery::__toString().
    return new SelectQueryResult($this->apiCall('query?q=' . (string) $query));
  }

  /**
   * {@inheritdoc}
   */
  public function queryAll(SelectQueryInterface $query) {
    return new SelectQueryResult($this->apiCall('queryAll?q=' . (string) $query));
  }

  /**
   * {@inheritdoc}
   */
  public function queryMore(SelectQueryResult $results) {
    if ($results->done()) {
      return new SelectQueryResult([
        'totalSize' => $results->size(),
        'done' => TRUE,
        'records' => [],
      ]);
    }
    $version_path = parse_url($this->authProvider->getApiEndpoint(), PHP_URL_PATH);
    $next_records_url = str_replace($version_path, '', $results->nextRecordsUrl());
    return new SelectQueryResult($this->apiCall($next_records_url));
  }

  /**
   * {@inheritdoc}
   */
  public function objectDescribe($name, $reset = FALSE) {
    if (empty($name)) {
      throw new \Exception('No name provided to describe');
    }

    if (!$reset && ($cache = $this->cache->get('salesforce:object:' . $name))) {
      return $cache->data;
    }
    else {
      $response = new RestResponseDescribe($this->apiCall("sobjects/{$name}/describe", [], 'GET', TRUE));
      $this->cache->set('salesforce:object:' . $name, $response, $this->getRequestTime() + $this->getShortTermCacheLifetime(), ['salesforce']);
      return $response;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function objectCreate($name, array $params) {
    $response = $this->apiCall("sobjects/{$name}", $params, 'POST', TRUE);
    $data = $response->data;
    return new SFID($data['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function objectUpsert($name, $key, $value, array $params) {
    // If key is set, remove from $params to avoid UPSERT errors.
    if (isset($params[$key])) {
      unset($params[$key]);
    }

    $response = $this->apiCall("sobjects/{$name}/{$key}/{$value}", $params, 'PATCH', TRUE);

    // On update, upsert method returns an empty body. Retrieve object id, so
    // that we can return a consistent response.
    if ($response->getStatusCode() == 204) {
      // We need a way to allow callers to distinguish updates and inserts. To
      // that end, cache the original response and reset it after fetching the
      // ID.
      $this->original_response = $response;
      $sf_object = $this->objectReadbyExternalId($name, $key, $value);
      return $sf_object->id();
    }
    $data = $response->data;
    return new SFID($data['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function objectUpdate($name, $id, array $params) {
    $this->apiCall("sobjects/{$name}/{$id}", $params, 'PATCH');
  }

  /**
   * {@inheritdoc}
   */
  public function objectRead($name, $id) {
    return new SObject($this->apiCall("sobjects/{$name}/{$id}"));
  }

  /**
   * {@inheritdoc}
   */
  public function objectReadbyExternalId($name, $field, $value) {
    return new SObject($this->apiCall("sobjects/{$name}/{$field}/{$value}"));
  }

  /**
   * {@inheritdoc}
   */
  public function objectDelete($name, $id, $throw_exception = FALSE) {
    try {
      $this->apiCall("sobjects/{$name}/{$id}", [], 'DELETE');
    }
    catch (RequestException $e) {
      if ($throw_exception || $e->getResponse()->getStatusCode() != 404) {
        throw $e;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDeleted($type, $startDate, $endDate) {
    return $this->apiCall("sobjects/{$type}/deleted/?start={$startDate}&end={$endDate}");
  }

  /**
   * {@inheritdoc}
   */
  public function listResources() {
    return new RestResponseResources($this->apiCall('', [], 'GET', TRUE));
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdated($name, $start = NULL, $end = NULL) {
    if (empty($start)) {
      $start = strtotime('-29 days');
    }
    $start = urlencode(gmdate(DATE_ATOM, $start));

    if (empty($end)) {
      $end = time();
    }
    $end = urlencode(gmdate(DATE_ATOM, $end));

    return $this->apiCall("sobjects/{$name}/updated/?start=$start&end=$end");
  }

  /**
   * {@inheritdoc}
   */
  public function getRecordTypes($name = NULL, $reset = FALSE) {
    if (!$reset && ($cache = $this->cache->get('salesforce:record_types'))) {
      $record_types = $cache->data;
    }
    else {
      $query = new SelectQuery('RecordType');
      $query->fields = ['Id', 'Name', 'DeveloperName', 'SobjectType'];
      $result = $this->query($query);
      $record_types = [];
      foreach ($result->records() as $rt) {
        $record_types[$rt->field('SobjectType')][$rt->field('DeveloperName')] = $rt;
      }
      $this->cache->set('salesforce:record_types', $record_types, $this->getRequestTime() + $this->getShortTermCacheLifetime(), ['salesforce']);
    }

    if ($name != NULL) {
      if (!isset($record_types[$name])) {
        return FALSE;
      }
      return $record_types[$name];
    }
    return $record_types ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecordTypeIdByDeveloperName($name, $devname, $reset = FALSE) {
    $record_types = $this->getRecordTypes($name, $reset);
    if (empty($record_types[$devname])) {
      return FALSE;
    }
    return $record_types[$devname]->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectTypeName(SFID $id) {
    $prefix = substr((string) $id, 0, 3);
    $describe = $this->objects();
    foreach ($describe as $object) {
      if ($prefix == $object['keyPrefix']) {
        return $object['name'];
      }
    }
    return FALSE;
  }

  /**
   * Returns REQUEST_TIME.
   *
   * @return int
   *   The REQUEST_TIME server variable.
   */
  protected function getRequestTime() {
    return $this->time->getRequestTime();
  }

}
