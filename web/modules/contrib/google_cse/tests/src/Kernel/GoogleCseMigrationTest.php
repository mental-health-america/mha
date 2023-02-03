<?php

namespace Drupal\Tests\google_cse\Kernel;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests Google CSE config migration.
 *
 * @group google_cse
 */
class GoogleCseMigrationTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'google_cse',
    'search',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    if (!class_exists('vfsStream')) {
      $this->markTestSkipped('vfsStream class not available. Please install composer package mikey179/vfsstream to run the tests');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // Since the schema is missing, this allows us to skip the schema check and
    // test our transformations.
    'search.page.google_cse_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('google_cse'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Asserts that Google CSE configuration is migrated.
   */
  public function testGoogleCseMigration() {
    $this->executeMigration('d7_google_cse');
    $this->assertEquals(
    [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [
        'module' => [
          0 => 'google_cse',
        ],
      ],
      'id' => 'google_cse_search',
      'label' => 'Google CSE Search',
      'path' => NULL,
      'weight' => 0,
      'plugin' => 'google_cse_search',
      'configuration' => [
        'results_prefix' => 'some prefix',
        'results_suffix' => 'some suffix',
        'cx' => 'abcgooglecustom',
        'results_tab' => 'somecustomname',
        'results_searchbox_width' => '608',
        'results_searchbox_width' => '47',
        'cof_here' => 'FORID:10',
        'cof_google' => 'FORID:1',
        'custom_results_display' => 'two-column',
        'results_display' => 'here',
        'results_display_images' => 1,
        'custom_css' => 'some stylesheet',
      ],
    ],
    array_diff_key($this->config('search.page.google_cse_search')
      ->getRawData(), ['uuid' => 'uuid']));
  }

}
