<?php

namespace Drupal\Tests\svg_maps\Functional;

use Behat\Mink\Exception\ExpectationException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group svg_maps
 */
class LoadTest extends BrowserTestBase {

  protected $defaultTheme = 'stable';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['svg_maps'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   * @throws EntityStorageException
   */
  protected function setUp() {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the home page loads with a 200 response.
   * @throws ExpectationException
   */
  public function testLoad() {
    $this->drupalGet(Url::fromRoute('<front>'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
