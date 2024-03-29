<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestLabel;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests the formatters functionality.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceFormatterTest extends EntityKernelTestBase {

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * The name of the field used in this test.
   *
   * @var string
   */
  protected $fieldName = 'field_test';

  /**
   * The entity to be referenced in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $referencedEntity;

  /**
   * The entity that's not yet saved to its persistent storage to be referenced.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $unsavedReferencedEntity;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['dynamic_entity_reference'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Use Classy theme for testing markup output.
    \Drupal::service('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    // Grant the 'view test entity' permission.
    $this->installConfig(['user']);
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('view test entity')
      ->save();

    // The label formatter rendering generates links, so build the router.
    $this->container->get('router.builder')->rebuild();

    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'dynamic_entity_reference',
      'entity_type' => $this->entityType,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          $this->entityType,
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'label' => 'Field test',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [],
      ],
    ])->save();

    // Set up a field, so that the entity that'll be referenced bubbles up a
    // cache tag when rendering it entirely.
    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => $this->entityType,
      'type' => 'text',
      'settings' => [],
    ])->save();
    FieldConfig::create([
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'field_name' => 'body',
      'label' => 'Body',
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => $this->entityType,
      'bundle' => $this->bundle,
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->setComponent('body', [
        'type' => 'text_default',
        'settings' => [],
      ])
      ->save();

    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
    ])->save();

    // Create the entity to be referenced.
    $this->referencedEntity = EntityTest::create(['name' => $this->randomMachineName()]);
    $this->referencedEntity->body = [
      'value' => '<p>Hello, world!</p>',
      'format' => 'full_html',
    ];
    $this->referencedEntity->save();

    // Create another entity to be referenced but do not save it.
    $this->unsavedReferencedEntity = EntityTest::create(['name' => $this->randomMachineName()]);
    $this->unsavedReferencedEntity->body = [
      'value' => '<p>Hello, unsaved world!</p>',
      'format' => 'full_html',
    ];
  }

  /**
   * Assert unaccessible items don't change the data of the fields.
   */
  public function testAccess() {
    // Revoke the 'view test entity' permission for this test.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->revokePermission('view test entity')
      ->save();

    $field_name = $this->fieldName;

    $referencing_entity = EntityTest::create(['name' => $this->randomMachineName()]);
    $referencing_entity->save();
    $referencing_entity->{$field_name}->entity = $this->referencedEntity;

    // Assert user doesn't have access to the entity.
    $this->assertFalse($this->referencedEntity->access('view'), 'Current user does not have access to view the referenced entity.');

    $formatter_manager = $this->container->get('plugin.manager.field.formatter');

    $entity_type_manager = \Drupal::entityTypeManager();
    // Get all the existing formatters.
    foreach ($formatter_manager->getOptions('dynamic_entity_reference') as $formatter => $name) {
      // Set formatter type for the 'full' view mode.
      $this->getEntityDisplay($this->entityType, $this->bundle, 'default')->setComponent($field_name, [
        'type' => $formatter,
        'settings' => $formatter == 'dynamic_entity_reference_entity_view' ? ['view_mode' => [$referencing_entity->getEntityTypeId() => 'default']] : [],
      ])
        ->save();

      // Invoke entity view.
      $entity_type_manager->getViewBuilder($referencing_entity->getEntityTypeId())->view($referencing_entity, 'default');

      // Verify the un-accessible item still exists.
      $this->assertEquals($referencing_entity->{$field_name}->target_id, $this->referencedEntity->id(), (string) new FormattableMarkup('The un-accessible item still exists after @name formatter was executed.', ['@name' => $name]));
    }
  }

  /**
   * Tests the ID formatter.
   */
  public function testIdFormatter() {
    $formatter = 'dynamic_entity_reference_entity_id';
    $build = $this->buildRenderArray([
      $this->referencedEntity,
      $this->unsavedReferencedEntity,
    ], $formatter);

    $this->assertEquals($build[0]['#plain_text'], $this->referencedEntity->id(), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEquals($build[0]['#cache']['tags'], $this->referencedEntity->getCacheTags(), sprintf('The %s formatter has the expected cache tags.', $formatter));
    $this->assertTrue(!isset($build[1]), sprintf('The markup returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));
  }

  /**
   * Tests the entity formatter.
   */
  public function testEntityFormatter() {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $formatter = 'dynamic_entity_reference_entity_view';
    $build = $this->buildRenderArray([
      $this->referencedEntity,
      $this->unsavedReferencedEntity,
    ],
      $formatter,
      [
        $this->referencedEntity->getEntityTypeId() => [
          'view_mode' => 'default',
          'link' => FALSE,
        ],
        $this->unsavedReferencedEntity->getEntityTypeId() => [
          'view_mode' => 'default',
          'link' => FALSE,
        ],
      ]);

    // Test the first field item.
    $expected_rendered_name_field_1 = '
            <div>' . $this->referencedEntity->label() . '</div>
      ';
    $expected_rendered_body_field_1 = '
  <div>
    <div>Body</div>
              <div><p>Hello, world!</p></div>
          </div>
';
    $renderer->renderRoot($build[0]);
    $this->assertEquals($build[0]['#markup'], 'default | ' . $this->referencedEntity->label() . $expected_rendered_name_field_1 . $expected_rendered_body_field_1, sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $expected_cache_tags = Cache::mergeTags(\Drupal::entityTypeManager()->getViewBuilder($this->entityType)->getCacheTags(), $this->referencedEntity->getCacheTags());
    $expected_cache_tags = Cache::mergeTags($expected_cache_tags, FilterFormat::load('full_html')->getCacheTags());
    $this->assertEquals($build[0]['#cache']['tags'], $expected_cache_tags, (string) new FormattableMarkup('The @formatter formatter has the expected cache tags.', ['@formatter' => $formatter]));

    // Test the second field item.
    $expected_rendered_name_field_2 = '
            <div>' . $this->unsavedReferencedEntity->label() . '</div>
      ';
    $expected_rendered_body_field_2 = '
  <div>
    <div>Body</div>
              <div><p>Hello, unsaved world!</p></div>
          </div>
';

    $renderer->renderRoot($build[1]);
    $this->assertEquals($build[1]['#markup'], 'default | ' . $this->unsavedReferencedEntity->label() . $expected_rendered_name_field_2 . $expected_rendered_body_field_2, sprintf('The markup returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));
  }

  /**
   * Tests the label formatter.
   */
  public function testLabelFormatter() {
    $this->installEntitySchema('entity_test_label');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $formatter = 'dynamic_entity_reference_label';

    // The 'link' settings is TRUE by default.
    $build = $this->buildRenderArray([
      $this->referencedEntity,
      $this->unsavedReferencedEntity,
    ], $formatter);

    $expected_field_cacheability = [
      'contexts' => [],
      'tags' => [],
      'max-age' => Cache::PERMANENT,
    ];
    $this->assertEquals($build['#cache'], $expected_field_cacheability, 'The field render array contains the entity access cacheability metadata');

    $expected_item_1 = [
      '#type' => 'link',
      '#title' => $this->referencedEntity->label(),
      '#url' => $this->referencedEntity->toUrl(),
      '#options' => $this->referencedEntity->toUrl()->getOptions(),
      '#cache' => [
        'contexts' => [
          'user.permissions',
        ],
        'tags' => $this->referencedEntity->getCacheTags(),
      ],
    ];
    $this->assertEquals($renderer->renderRoot($build[0]), $renderer->renderRoot($expected_item_1), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEquals(CacheableMetadata::createFromRenderArray($build[0]), CacheableMetadata::createFromRenderArray($expected_item_1));

    // The second referenced entity is "autocreated", therefore not saved and
    // lacking any URL info.
    $expected_item_2 = [
      '#plain_text' => $this->unsavedReferencedEntity->label(),
      '#cache' => [
        'contexts' => [
          'user.permissions',
        ],
        'tags' => $this->unsavedReferencedEntity->getCacheTags(),
        'max-age' => Cache::PERMANENT,
      ],
    ];
    $this->assertEquals($build[1], $expected_item_2, sprintf('The render array returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));

    // Test with the 'link' setting set to FALSE.
    $build = $this->buildRenderArray([
      $this->referencedEntity,
      $this->unsavedReferencedEntity,
    ],
      $formatter, ['link' => FALSE]);
    $this->assertEquals($build[0]['#plain_text'], $this->referencedEntity->label(), sprintf('The markup returned by the %s formatter is correct for an item with a saved entity.', $formatter));
    $this->assertEquals($build[1]['#plain_text'], $this->unsavedReferencedEntity->label(), sprintf('The markup returned by the %s formatter is correct for an item with a unsaved entity.', $formatter));

    // Test an entity type that doesn't have any link templates, which means
    // \Drupal\Core\Entity\EntityInterface::urlInfo() will throw an exception
    // and the label formatter will output only the label instead of a link.
    $field_storage_config = FieldStorageConfig::loadByName($this->entityType, $this->fieldName);
    $field_storage_config->setSetting('target_type', 'entity_test_label');
    $field_storage_config->save();

    $referenced_entity_with_no_link_template = EntityTestLabel::create([
      'name' => $this->randomMachineName(),
    ]);
    $referenced_entity_with_no_link_template->save();

    $build = $this->buildRenderArray([$referenced_entity_with_no_link_template], $formatter, ['link' => TRUE]);
    $this->assertEquals($build[0]['#plain_text'], $referenced_entity_with_no_link_template->label(), sprintf('The markup returned by the %s formatter is correct for an entity type with no valid link template.', $formatter));
  }

  /**
   * Renders the same entity referenced from different places.
   */
  public function testEntityReferenceRecursiveProtectionWithManyRenderedEntities() {
    $formatter = 'dynamic_entity_reference_entity_view';
    $view_builder = $this->entityTypeManager->getViewBuilder($this->entityType);

    // Set the default view mode to use the 'entity_reference_entity_view'
    // formatter.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay($this->entityType, $this->bundle)
      ->setComponent($this->fieldName, [
        'type' => $formatter,
      ])
      ->save();

    $storage = $this->entityTypeManager->getStorage($this->entityType);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
    $referenced_entity = $storage->create(['name' => $this->randomMachineName()]);

    $referencing_entities = array_map(function () use ($storage, $referenced_entity) {
      $referencing_entity = $storage->create([
        'name' => $this->randomMachineName(),
        $this->fieldName => $referenced_entity,
      ]);
      $referencing_entity->save();
      return $referencing_entity;
    }, range(0, 29));

    $build = $view_builder->viewMultiple($referencing_entities, 'default');
    $output = $this->render($build);

    // The title of entity_test entities is printed twice by default, so we have
    // to multiply the formatter's recursive rendering protection limit by 2.
    $expected_occurrences = 30 * 2;
    $actual_occurrences = substr_count($output, $referenced_entity->get('name')->value);
    $this->assertEquals($expected_occurrences, $actual_occurrences);
  }

  /**
   * Sets field values and returns a render array as built by field list view.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $referenced_entities
   *   An array of entity objects that will be referenced.
   * @param string $formatter
   *   The formatted plugin that will be used for building the render array.
   * @param array $formatter_options
   *   Settings specific to the formatter. Defaults to the formatter's default
   *   settings.
   *
   * @see \Drupal\Core\Field\FieldItemListInterface::view()
   *
   * @return array
   *   A render array.
   */
  protected function buildRenderArray(array $referenced_entities, $formatter, array $formatter_options = []) {
    // Create the entity that will have the entity reference field.
    $referencing_entity = EntityTest::create(['name' => $this->randomMachineName()]);

    $items = $referencing_entity->get($this->fieldName);

    // Assign the referenced entities.
    foreach ($referenced_entities as $referenced_entity) {
      $items[] = ['entity' => $referenced_entity];
    }

    // Build the renderable array for the field.
    return $items->view([
      'type' => $formatter,
      'settings' => $formatter_options,
    ]);
  }

  /**
   * Returns the entity view display associated with a bundle and view mode.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $view_mode
   *   The view mode, or 'default' to retrieve the 'default' display object for
   *   this bundle.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The entity view display associated with the view mode.
   */
  protected function getEntityDisplay($entity_type, $bundle, $view_mode) {
    // Try loading the display from configuration.
    $display = EntityViewDisplay::load($entity_type . '.' . $bundle . '.' . $view_mode);

    // If not found, create a fresh display object. We do not preemptively
    // create new entity_view_display configuration entries for each existing
    // entity type and bundle whenever a new view mode becomes available.
    // Instead, configuration entries are only created when a display object is
    // explicitly configured and saved.
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $view_mode,
        'status' => TRUE,
      ]);
    }

    return $display;
  }

}
