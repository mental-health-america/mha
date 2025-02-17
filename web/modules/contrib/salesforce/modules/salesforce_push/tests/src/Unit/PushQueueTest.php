<?php

namespace Drupal\Tests\salesforce_push\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Database\Schema;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_push\PushQueue;
use Drupal\salesforce_push\PushQueueProcessorInterface;
use Drupal\salesforce_push\PushQueueProcessorPluginManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Test Object instantitation.
 *
 * @coversDefaultClass \Drupal\salesforce_push\PushQueue
 *
 * @group salesforce_push
 */
class PushQueueTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  static protected $modules = ['salesforce_push'];

  protected $configFactory;

  protected $database;

  protected $entityStorage;

  protected $entityTypeManager;

  protected $eventDispatcher;

  protected $mappedObjectStorage;

  protected $mappingStorage;

  protected $push_queue_processor_plugin_manager;

  protected $queryRange;

  protected $queue;

  protected $schema;

  protected $state;

  protected $string_translation;

  protected $time;

  protected $updateQuery;

  protected $worker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->schema = $this->getMockBuilder(Schema::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->schema->expects($this->any())
      ->method('tableExists')
      ->willReturn(TRUE);
    $this->database = $this->getMockBuilder(Connection::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->database->expects($this->any())
      ->method('schema')
      ->willReturn($this->schema);
    $this->state = $this->getMockBuilder(StateInterface::class)->getMock();
    $this->push_queue_processor_plugin_manager =
      $this->getMockBuilder(PushQueueProcessorPluginManager::class)
        ->disableOriginalConstructor()
        ->getMock();
    $this->entityTypeManager =
      $this->getMockBuilder(EntityTypeManagerInterface::class)->getMock();
    $this->eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::CLASS)
      ->getMock();
    $this->eventDispatcher->expects($this->any())
      ->method('dispatch')
      ->willReturnArgument(0);
    $this->string_translation = $this->getMockBuilder(TranslationInterface::class)
      ->getMock();
    $this->time = $this->getMockBuilder(TimeInterface::class)->getMock();

    $this->mappingStorage = $this->getMockBuilder(SalesforceMappingStorage::CLASS)
      ->disableOriginalConstructor()
      ->getMock();

    $this->mappedObjectStorage = $this->getMockBuilder(SqlEntityStorageInterface::CLASS)
      ->getMock();

    $this->entityStorage = $this->getMockBuilder(SqlEntityStorageInterface::CLASS)
      ->getMock();

    $this->entityTypeManager->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturnOnConsecutiveCalls(
        $this->mappingStorage,
        $this->mappedObjectStorage
      );

    // Mock config.
    $prophecy = $this->prophesize(Config::CLASS);
    $prophecy->get('global_push_limit', Argument::any())
      ->willReturn(PushQueue::DEFAULT_GLOBAL_LIMIT);
    $config = $prophecy->reveal();

    $prophecy = $this->prophesize(ConfigFactoryInterface::CLASS);
    $prophecy->get('salesforce.settings')->willReturn($config);
    $this->configFactory = $prophecy->reveal();

    $container = new ContainerBuilder();
    $container->set('database', $this->database);
    $container->set('state', $this->state);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('event_dispatcher', $this->eventDispatcher);
    $container->set('string_translation', $this->string_translation);
    $container->set('plugin.manager.salesforce_push_queue_processor', $this->push_queue_processor_plugin_manager);
    $container->set('datetime.time', $this->time);
    $container->set('config.factory', $this->configFactory);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::claimItem
   */
  public function testClaimItem() {
    $this->queue = PushQueue::create(\Drupal::getContainer());
    $this->expectException(\Exception::class);
    $this->queue->claimItem();
  }

  /**
   * @covers ::claimItems
   */
  public function testClaimItems() {
    $this->queue = PushQueue::create(\Drupal::getContainer());

    // Test claiming items.
    $items = [1, 2, 3];
    $this->queryRange = $this->getMockBuilder(StatementInterface::class)
      ->getMock();
    $this->queryRange->expects($this->once())
      ->method('fetchAllAssoc')
      ->willReturn($items);
    $this->database->expects($this->once())
      ->method('queryRange')
      ->willReturn($this->queryRange);

    $this->updateQuery = $this->getMockBuilder(Update::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->updateQuery->expects($this->once())
      ->method('fields')
      ->willReturn($this->updateQuery);
    $this->updateQuery->expects($this->any())
      ->method('condition')
      ->willReturn($this->updateQuery);
    $this->updateQuery->expects($this->once())
      ->method('execute')
      ->willReturn(TRUE);
    $this->database->expects($this->once())
      ->method('update')
      ->willReturn($this->updateQuery);

    $this->assertEquals($items, $this->queue->claimItems(0));
  }

  /**
   * @covers ::processQueues
   */
  public function testProcessQueue() {
    $mapping1 = $this->getMockBuilder(SalesforceMapping::CLASS)
      ->setConstructorArgs([
        ['id' => 1, 'push_limit' => 1, 'push_retries' => 1],
        'salesforce_mapping'
      ])
      ->getMock();
    $mapping1->expects($this->any())
      ->method('getNextPushTime')
      ->willReturn(0);

    $this->worker = $this->getMockBuilder(PushQueueProcessorInterface::class)
      ->getMock();
    $this->worker->expects($this->any())
      ->method('process')
      ->willReturn(NULL);
    $this->push_queue_processor_plugin_manager->expects($this->once())
      ->method('createInstance')
      ->willReturn($this->worker);

    $this->queue = $this->getMockBuilder(PushQueue::class)
      ->setMethods([
        'claimItems',
        'setName',
        'garbageCollection',
      ])
      ->setConstructorArgs([
        $this->database,
        $this->state,
        $this->push_queue_processor_plugin_manager,
        $this->entityTypeManager,
        $this->eventDispatcher,
        $this->time,
        $this->configFactory,
      ])
      ->getMock();

    $this->queue->expects($this->once())
      ->method('setName')
      ->willReturn(NULL);
    $this->queue->expects($this->any())
      ->method('garbageCollection')
      ->willReturn(NULL);
    $this->queue->expects($this->exactly(4))
      ->method('claimItems')
      ->willReturnOnConsecutiveCalls(
        [1], [2], [3], []
      );

    $this->assertEquals(3, $this->queue->processQueue($mapping1));

  }

}
