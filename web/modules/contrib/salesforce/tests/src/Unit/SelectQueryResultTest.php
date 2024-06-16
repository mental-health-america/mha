<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SFID;
use Drupal\Tests\UnitTestCase;

/**
 * Test Object instantitation.
 *
 * @group salesforce_pull
 */
class SelectQueryResultTest extends UnitTestCase {

  /**
   * Required modules.
   *
   * @var array
   */
  protected static $modules = ['salesforce'];

  /**
   * Query result.
   *
   * @var \Drupal\salesforce\SelectQueryResult
   */
  protected SelectQueryResult $sqr;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $result = [
      'totalSize' => 2,
      'done' => TRUE,
      'records' => [
        [
          'Id' => '1234567890abcde',
          'attributes' => ['type' => 'dummy'],
          'name' => 'Example',
        ],
        [
          'Id' => '1234567890abcdf',
          'attributes' => ['type' => 'dummy'],
          'name' => 'Example2',
        ],
      ],
    ];
    $this->sqr = new SelectQueryResult($result);
  }

  /**
   * Test object instantiation with good resultd.
   */
  public function testGoodId() {
    $this->assertTrue($this->sqr instanceof SelectQueryResult);
  }

  /**
   * Test object instantiation with non-existent ID.
   */
  public function testNoId() {
    $sfid = new SFID('1234567890abcdg');
    $this->assertFalse($this->sqr->record($sfid));
  }

}
