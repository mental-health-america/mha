<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\State\State;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Rest\RestResponseDescribe;
use Drupal\salesforce\Rest\RestResponseResources;
use Drupal\salesforce\SalesforceAuthProviderInterface;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use OAuth\OAuth2\Token\TokenInterface;

/**
 * @coversDefaultClass \Drupal\salesforce\Rest\RestClient
 * @group salesforce
 */
class RestClientTest extends UnitTestCase {

  /**
   * Required modules.
   *
   * @var array
   */
  protected static $modules = ['salesforce'];

  protected $authConfig;

  protected $authMan;

  protected $authProvider;

  protected $authToken;

  protected $cache;

  protected $client;

  protected $configFactory;

  protected $httpClient;

  protected $json;

  protected $methods;

  protected $salesforce_id;

  protected $state;

  protected $time;

  /**
   * Set up for each test.
   */
  public function setUp(): void {
    parent::setUp();
    $this->salesforce_id = '1234567890abcde';
    $this->methods = [
      'httpRequest',
    ];

    $this->httpClient = $this->getMockBuilder(Client::CLASS)->getMock();
    $this->configFactory =
      $this->getMockBuilder(ConfigFactory::CLASS)
        ->disableOriginalConstructor()
        ->getMock();
    $this->state =
      $this->getMockBuilder(State::CLASS)
        ->disableOriginalConstructor()
        ->getMock();
    $this->cache = $this->getMockBuilder(CacheBackendInterface::CLASS)
      ->getMock();
    $this->json = $this->getMockBuilder(Json::CLASS)->getMock();
    $this->time = $this->getMockBuilder(TimeInterface::CLASS)->getMock();
    $this->authToken = $this->getMockBuilder(TokenInterface::CLASS)->getMock();
    $this->authProvider = $this->getMockBuilder(SalesforceAuthProviderInterface::CLASS)
      ->disableOriginalConstructor()
      ->getMock();
    $this->authProvider->expects($this->any())
      ->method('getApiEndpoint')
      ->willReturn('https://example.com');
    $this->authConfig =
      $this->getMockBuilder(SalesforceAuthConfig::CLASS)
        ->disableOriginalConstructor()
        ->getMock();

    $this->authMan =
      $this->getMockBuilder(SalesforceAuthProviderPluginManager::CLASS)
        ->disableOriginalConstructor()
        ->getMock();
    $this->authMan->expects($this->any())
      ->method('getToken')
      ->willReturn($this->authToken);
    $this->authMan->expects($this->any())
      ->method('getProvider')
      ->willReturn($this->authProvider);
    $this->authMan->expects($this->any())
      ->method('getConfig')
      ->willReturn($this->authConfig);
    $this->authMan->expects($this->any())
      ->method('refreshToken')
      ->willReturn($this->authToken);
  }

  /**
   * @covers ::__construct
   */
  private function initClient($methods = NULL) {
    if (empty($methods)) {
      $methods = $this->methods;
    }
    $methods[] = 'getShortTermCacheLifetime';
    $methods[] = 'getLongTermCacheLifetime';

    $args = [
      $this->httpClient,
      $this->configFactory,
      $this->state,
      $this->cache,
      $this->json,
      $this->time,
      $this->authMan,
    ];

    $this->client = $this
      ->getMockBuilder(RestClient::CLASS)
      ->setMethods($methods)
      ->setConstructorArgs($args)
      ->getMock();

    $this->client->expects($this->any())
      ->method('getShortTermCacheLifetime')
      ->willReturn(0);

    $this->client->expects($this->any())
      ->method('getLongTermCacheLifetime')
      ->willReturn(0);
  }

  /**
   * @covers ::apiCall
   */
  public function testSimpleApiCall() {
    $this->initClient();

    // Test that an apiCall returns a json-decoded value.
    $body = ['foo' => 'bar'];
    $response = new GuzzleResponse(200, ['Content-Type' => 'application/json'], json_encode($body));

    $this->client->expects($this->any())
      ->method('httpRequest')
      ->willReturn($response);

    $result = $this->client->apiCall('');
    $this->assertEquals($result, $body);
  }

  /**
   * @covers ::apiCall
   */
  public function testNonJsonApiCall() {
    $this->initClient();

    // Test that an apiCall returns a CSV string value.
    $body = <<<EOT
    "Id","Name"
    "005R0000000UyrWIAS","Johnny B. Goode"
    "005R0000000GiwjIAC","Prince Rogers Nelson"
    "005R0000000GiwoIAC","Robert Allen Zimmerman"
    EOT;

    $response = new GuzzleResponse(200, ['Content-Type' => 'text/csv'], $body);

    $this->client->expects($this->any())
      ->method('httpRequest')
      ->willReturn($response);

    $result = $this->client->apiCall('');
    $this->assertEquals($result, $body);
  }

  /**
   * @covers ::apiCall
   */
  public function testExceptionApiCall() {
    $this->initClient();

    // Test that SF client throws an exception for non-200 response.
    $response = new GuzzleResponse(456);

    $this->client->expects($this->any())
      ->method('httpRequest')
      ->willReturn($response);

    $this->expectException(\Exception::class);
    $this->client->apiCall('');
  }

  /**
   * @covers ::apiCall
   */
  public function testReauthApiCall() {
    $this->initClient();

    // Test that apiCall does auto-re-auth after 401 response.
    $response_401 = new GuzzleResponse(401);
    $response_200 = new GuzzleResponse(200);

    // @todo this is extremely brittle, exposes complexity in underlying client. Refactor this.
    $this->client->expects($this->exactly(2))
      ->method('httpRequest')
      ->willReturnOnConsecutiveCalls(
        $response_401,
        $response_200
      );

    $this->client->apiCall('');
  }

  /**
   * @covers ::objects
   */
  public function testObjectsFromCache() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $objects = [
      'sobjects' => [
        'Test' => [
          'name' => 'Test',
          'updateable' => TRUE,
        ],
      ],
    ];
    $cache = (object) [
      'created' => time(),
      'data' => $objects,
    ];

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn($cache);

    // Get objects from cache:
    $this->assertEquals($cache->data['sobjects'], $this->client->objects());

  }

  /**
   * @covers ::objects
   */
  public function testObjectsFromApiCall() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $objects = [
      'sobjects' => [
        'Test' => [
          'name' => 'Test',
          'updateable' => TRUE,
        ],
      ],
    ];
    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($objects);

    // Get objects from apiCall()
    $this->assertEquals($objects['sobjects'], $this->client->objects());
  }

  /**
   * @covers ::query
   */
  public function testQuery() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $rawQueryResult = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [
        0 => [
          'attributes' => [
            'type' => 'Foo',
            'url' => 'Bar',
          ],
          'Id' => $this->salesforce_id,
        ],
      ],
    ];

    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($rawQueryResult);

    // @todo this doesn't seem like a very good test.
    $this->assertEquals(new SelectQueryResult($rawQueryResult), $this->client->query(new SelectQuery("")));
  }

  /**
   * @covers ::objectDescribe
   */
  public function testObjectDescribe() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $name = $this->randomMachineName();
    // @todo this is fugly, do we need a refactor on RestResponse?
    $restResponse = new RestResponse(
      new GuzzleResponse('200', ['Content-Type' => 'application/json'], json_encode([
        'name' => $name,
        'fields' => [
          [
            'name' => $this->randomMachineName(),
            'label' => 'Foo Bar',
            $this->randomMachineName() => $this->randomMachineName(),
            $this->randomMachineName() => [
              $this->randomMachineName() => $this->randomMachineName(),
              $this->randomMachineName() => $this->randomMachineName(),
            ],
          ],
          [
            'name' => $this->randomMachineName(),
          ],
        ],
      ]))
    );

    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($restResponse);

    // Test that we hit "apiCall" and get expected result:
    $result = $this->client->objectDescribe($name);
    $expected = new RestResponseDescribe($restResponse);
    $this->assertEquals($expected, $result);

    // Test that cache gets set correctly:
    $this->cache->expects($this->any())
      ->method('get')
      ->willReturn((object) [
        'data' => $expected,
        'created' => time(),
      ]);

    // Test that we hit cache when we call again.
    // (Otherwise, we'll blow the "once" condition)
    $this->assertEquals($expected, $this->client->objectDescribe($name));

    // @todo what happens when we provide a name for non-existent SF table?
    // 404 exception?
    // Test that we throw an exception if name is not provided.
    $this->expectException(\Exception::class);
    $this->client->objectDescribe('');
  }

  /**
   * @covers ::objectCreate
   */
  public function testObjectCreate() {
    $this->initClient(array_merge($this->methods, ['apiCall']));
    $restResponse = new RestResponse(
      new GuzzleResponse('200', ['Content-Type' => 'application/json'], json_encode([
        'id' => $this->salesforce_id,
      ]))
    );

    $sfid = new SFID($this->salesforce_id);
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($restResponse);

    // @todo this doesn't seem like a very good test.
    $this->assertEquals($sfid, $this->client->objectCreate('', []));

  }

  /**
   * @covers ::objectUpsert
   */
  public function testObjectUpsert() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
      'objectReadbyExternalId',
    ]));
    $createResponse = new RestResponse(
      new GuzzleResponse('200', ['Content-Type' => 'application/json'], json_encode([
        'id' => $this->salesforce_id,
      ])));

    $updateResponse = new RestResponse(new GuzzleResponse('204', [], ''));

    $sfid = new SFID($this->salesforce_id);
    $sobject = new SObject([
      'id' => $this->salesforce_id,
      'attributes' => ['type' => 'dummy'],
    ]);
    $this->client->expects($this->exactly(2))
      ->method('apiCall')
      ->willReturnOnConsecutiveCalls(
        $createResponse,
        $updateResponse
      );

    $this->client->expects($this->once())
      ->method('objectReadbyExternalId')
      ->willReturn($sobject);

    // Ensure both upsert-create and upsert-update return the same value.
    $this->assertEquals($sfid, $this->client->objectUpsert('', '', '', []));
    $this->assertEquals($sfid, $this->client->objectUpsert('', '', '', []));
  }

  /**
   * @covers ::objectUpdate
   */
  public function testObjectUpdate() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn(NULL);
    $this->assertNull($this->client->objectUpdate('', '', []));
  }

  /**
   * @covers ::objectRead
   */
  public function testObjectRead() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $rawData = [
      'id' => $this->salesforce_id,
      'attributes' => ['type' => 'dummy'],
    ];
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($rawData);
    $this->assertEquals(new SObject($rawData), $this->client->objectRead('', ''));
  }

  /**
   * @covers ::objectReadbyExternalId
   */
  public function testObjectReadbyExternalId() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $rawData = [
      'id' => $this->salesforce_id,
      'attributes' => ['type' => 'dummy'],
    ];
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($rawData);
    $this->assertEquals(new SObject($rawData), $this->client->objectReadByExternalId('', '', ''));
  }

  /**
   * @covers ::objectDelete
   *
   * 3 tests for objectDelete:
   *   1. test that a successful delete returns null
   *   2. test that a 404 response gets eaten
   *   3. test that any other error response percolates.
   */
  public function testObjectDeleteSuccess() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));

    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn(NULL);

    $this->assertNull($this->client->objectDelete('', ''));
  }

  /**
   * @covers ::objectDelete
   */
  public function testObjectDelete404() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));

    $exception404 = new RequestException('', new GuzzleRequest('GET', ''), new GuzzleResponse(404, [], ''));
    $this->client->expects($this->once())
      ->method('apiCall')
      ->will($this->throwException($exception404));

    $this->assertNull($this->client->objectDelete('', ''));
  }

  /**
   * @covers ::objectDelete
   */
  public function testObjectDeleteException() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));

    // Test the objectDelete throws any other exception.
    $exceptionOther = new RequestException('', new GuzzleRequest('GET', ''), new GuzzleResponse(456, [], ''));
    $this->client->expects($this->once())
      ->method('apiCall')
      ->will($this->throwException($exceptionOther));
    $this->expectException(RequestException::class);
    $this->client->objectDelete('', '');
  }

  /**
   * @covers ::listResources
   */
  public function testListResources() {
    $this->initClient(array_merge($this->methods, [
      'apiCall',
    ]));
    $restResponse = new RestResponse(new GuzzleResponse('204', ['Content-Type' => 'application/json'], json_encode([
      'foo' => 'bar',
      'zee' => 'bang',
    ])));
    $this->client->expects($this->once())
      ->method('apiCall')
      ->willReturn($restResponse);
    $this->assertEquals(new RestResponseResources($restResponse), $this->client->listResources());
  }

  /**
   * @covers ::getRecordTypes
   */
  public function testGetRecordTypesAll() {
    $this->initClient(array_merge($this->methods, ['query']));
    $sObjectType = $this->randomMachineName();
    $developerName = $this->randomMachineName();

    $rawQueryResult = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [
        0 => [
          'attributes' => [
            'type' => 'Foo',
            'url' => 'Bar',
          ],
          'SobjectType' => $sObjectType,
          'DeveloperName' => $developerName,
          'Id' => $this->salesforce_id,
        ],
      ],
    ];
    $recordTypes = [
      $sObjectType => [
        $developerName =>
          new SObject($rawQueryResult['records'][0]),
      ],
    ];

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);
    $this->client->expects($this->once())
      ->method('query')
      ->willReturn(new SelectQueryResult($rawQueryResult));

    $this->assertEquals($recordTypes, $this->client->getRecordTypes());
  }

  /**
   * @covers ::getRecordTypes
   */
  public function testGetRecordTypesSingle() {
    $this->initClient(array_merge($this->methods, ['query']));
    $sObjectType = $this->randomMachineName();
    $developerName = $this->randomMachineName();

    $rawQueryResult = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [
        0 => [
          'attributes' => [
            'type' => 'Foo',
            'url' => 'Bar',
          ],
          'SobjectType' => $sObjectType,
          'DeveloperName' => $developerName,
          'Id' => $this->salesforce_id,
        ],
      ],
    ];
    $recordTypes = [
      $sObjectType => [
        $developerName =>
          new SObject($rawQueryResult['records'][0]),
      ],
    ];
    $cache = (object) [
      'created' => time(),
      'data' => $recordTypes,
    ];

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn($cache);

    $this->assertEquals($recordTypes[$sObjectType], $this->client->getRecordTypes($sObjectType));
  }

  /**
   * @covers ::getRecordTypes
   */
  public function testGetRecordTypesNonexistent() {
    $this->initClient(array_merge($this->methods, ['query']));
    $sObjectType = $this->randomMachineName();
    $developerName = $this->randomMachineName();

    $rawQueryResult = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [
        0 => [
          'attributes' => [
            'type' => 'Foo',
            'url' => 'Bar',
          ],
          'SobjectType' => $sObjectType,
          'DeveloperName' => $developerName,
          'Id' => $this->salesforce_id,
        ],
      ],
    ];
    $recordTypes = [
      $sObjectType => [
        $developerName =>
          new SObject($rawQueryResult['records'][0]),
      ],
    ];
    $cache = (object) [
      'created' => time(),
      'data' => $recordTypes,
    ];

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn($cache);

    $this->assertFalse($this->client->getRecordTypes('fail'));
  }

}
