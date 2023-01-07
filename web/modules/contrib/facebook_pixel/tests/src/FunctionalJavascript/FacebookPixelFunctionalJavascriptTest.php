<?php

namespace Drupal\Tests\facebook_pixel\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the facebook_pixel javascript functionalities.
 *
 * @group facebook_pixel
 */
class FacebookPixelFunctionalJavascriptTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'test_page_test',
    'node',
    'facebook_pixel',
  ];

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('page.front', '/test-page')->save();

    $this->user = $this->drupalCreateUser([]);
    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests if the FB Pixel script is loaded.
   */
  public function testFbPixelJsLoaded() {
    $session = $this->assertSession();
    // Activate fb Pixel globally:
    $this->config('facebook_pixel.settings')->set('visibility.request_path_pages', '')->save();
    $this->config('facebook_pixel.settings')->set('facebook_id', '0000000')->save();

    $this->drupalGet('<front>');
    $session->elementExists('css', 'script[src*="facebook_pixel.js"]');
  }

  /**
   * Tests the testAdvancedOptOut functionality.
   */
  public function testAdvancedOptOut() {
    $session = $this->assertSession();
    /**
     * @var \Behat\Mink\Driver\Selenium2Driver $driver
     */
    $driver = $this->getSession()->getDriver();

    // Set test settings:
    $this->config('facebook_pixel.settings')->set('visibility.request_path_pages', '')->save();
    $this->config('facebook_pixel.settings')->set('facebook_id', '0000000')->save();
    $this->config('facebook_pixel.settings')->set('privacy.fb_disable_advanced', 'true')->save();

    // Go to front and check if the script is loaded:
    $this->drupalGet('<front>');
    $session->elementExists('css', 'script[src*="facebook_pixel.js"]');

    // Fire fbOptout script:
    $script = "fbOptout();";
    $driver->executeScript($script);
    drupal_flush_all_caches();

    // Check if non tracking cookie is set and has the correct value:
    $cookie = $driver->getCookie('fb-disable');
    $this->assertSame('true', $cookie);
  }

  // @codingStandardsIgnoreStart
  //@todo Implement this test:
  // public function testDisableNoScriptFallback() {
  //   $session = $this->assertSession();
  //   /**
  //    * @var \Behat\Mink\Driver\Selenium2Driver $driver
  //    */
  //   $driver = $this->getSession()->getDriver();

  //   // Set test settings:
  //   $this->config('facebook_pixel.settings')->set('visibility.request_path_pages', '')->save();
  //   $this->config('facebook_pixel.settings')->set('facebook_id', '0000000')->save();
  //   $this->config('facebook_pixel.settings')->set('privacy.disable_noscript_img', 'true')->save();
  // }
  // @codingStandardsIgnoreEnd

}
