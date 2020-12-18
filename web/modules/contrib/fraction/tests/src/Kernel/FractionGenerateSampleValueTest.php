<?php

namespace Drupal\Tests\fraction\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * Class to test generating and setting sample values.
 *
 * @group Fraction
 */
class FractionGenerateSampleValueTest extends FieldKernelTestBase {

  /**
   * Field name to use.
   */
  const FIELD_NAME = 'field_fraction_test';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'fraction'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => 'article',
      'label' => 'Article',
    ])->save();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => self::FIELD_NAME,
      'type' => 'fraction',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => self::FIELD_NAME,
      'bundle' => 'article',
    ])->save();
  }

  /**
   * Tests setting the sample value.
   */
  public function testGenerateSampleValue() {
    $node = Node::create([
      'type' => 'article',
      'title' => 'test',
    ]);
    $node->save();
    $node->get(self::FIELD_NAME)->generateSampleItems();
    $this->entityValidateAndSave($node);
    $result = $node->get(self::FIELD_NAME)->getValue();
    $result = reset($result);
    $this->assertTrue(is_array($result));
    $this->assertArrayHasKey('numerator', $result);
    $this->assertArrayHasKey('denominator', $result);
    $this->assertTrue($result['denominator'] > 0, TRUE);
  }

}
