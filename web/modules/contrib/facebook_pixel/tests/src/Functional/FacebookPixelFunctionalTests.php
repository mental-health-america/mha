<?php

namespace Drupal\Tests\facebook_pixel\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * This class provides testing for the facebook_pixel browser functionalities.
 *
 * @group facebook_pixel
 */
class FacebookPixelFunctionalTests extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'facebook_pixel',
    'test_page_test',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * An admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminuser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The fb pixel dummy account key for testing.
   *
   * @var string
   */
  protected $fbPixelKey = '0000000';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Use the test page as the front page:
    $this->config('system.site')->set('page.front', '/test-page')->save();
    // Create an user:
    $this->user = $this->drupalCreateUser([
      'configure facebook_pixel',
      'use php for page_visibility',
      'access content',
    ]);

    // Create an admin user:
    $this->adminuser = $this->drupalCreateUser(['access content']);
    $this->adminuser->addRole($this->createAdminRole('administrator', 'administrator'));
    $this->adminuser->save();

    // Login as regular authenticated user by default:
    $this->drupalLogin($this->user);
  }

  /**
   * Tests if the module installation, won't break the site.
   */
  public function testInstallation() {
    $session = $this->assertSession();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
  }

  /**
   * Tests if uninstalling the module, won't break the site.
   */
  public function testUninstallation() {
    $this->drupalLogout();
    $this->drupalLogin($this->adminuser);
    // Go to uninstallation page an uninstall facebook_pixel:
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/modules/uninstall');
    $session->statusCodeEquals(200);
    $page->checkField('edit-uninstall-facebook-pixel');
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    // Confirm deinstall:
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    $session->pageTextContains('The selected modules have been uninstalled.');
  }

  /**
   * Tests if the config form is unaccessible as an anonymous user.
   */
  public function testFbAccessFormAsAnonymous() {
    $this->drupalLogout();
    $session = $this->assertSession();

    // Go to settings and see if the anonymous user has no access:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(403);
  }

  /**
   * Tests if the config form is accessible as an admin user.
   */
  public function testFbAccessFormAsAdmin() {
    $session = $this->assertSession();
    $this->drupalLogout();
    $this->drupalLogin($this->adminuser);

    // Go to settings and see if an admin has access:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
  }

  /**
   * Tests if setting the account key via form works as intended.
   */
  public function testFbFormAccountKey() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Go to settings and see if they exist:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Fill the account key:
    $page->fillField('edit-facebook-id', $this->fbPixelKey);
    $page->pressButton('edit-submit');

    // Check if the configuration was saved:
    $session->statusCodeEquals(200);
    $session->pageTextContains('The configuration options have been saved.');

    // Check if the account key is set in the config:
    $this->assertEquals($this->fbPixelKey, \Drupal::config('facebook_pixel.settings')->get('facebook_id'));
  }

  /**
   * Tests if the facebook_pixel scripts exists.
   */
  public function testFbScriptsExist() {
    $session = $this->assertSession();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // See if account key is the same as in the config:
    $this->assertEquals($this->fbPixelKey, $this->config('facebook_pixel.settings')->get('facebook_id'));

    // Check script exists:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // By default, anonymous users should have the same result:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the fb pixel noscript part has the correct values and exists.
   */
  public function testFbNoScript() {
    $session = $this->assertSession();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to front-page and see if the account key is present in the noscript:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', 'noscript>img[src*= ' . $this->fbPixelKey . ']');
  }

  /**
   * Test if disabling the no script fallback, will remove the noscript element.
   */
  public function testDisableNoScriptFallback() {
    $session = $this->assertSession();
    // Set test settings:
    $this->config('facebook_pixel.settings')->set('visibility.request_path_pages', '')->save();
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    $this->config('facebook_pixel.settings')->set('privacy.disable_noscript_img', 'true')->save();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', 'noscript>img[src*= ' . $this->fbPixelKey . ']');

  }

  /**
   * Tests if the script is not loaded on a specific path with a slash.
   */
  public function testExclusionByPathWithSlashUrl() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Don't track on test-page:
    $page->fillField('edit-facebook-pixel-visibility-request-path-mode-all-pages', 'all_pages');
    $page->fillField('edit-facebook-pixel-visibility-request-path-pages', '/test-page');
    $page->pressButton('edit-submit');

    // See if script won't load for our authenticated user:
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");

    // By default, anonymous users should have the same result.
    $this->drupalLogout();
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is loaded on a specific path with a slash.
   */
  public function testInclusionByPathWithSlashUrl() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Track on test-page:
    $page->fillField('edit-facebook-pixel-visibility-request-path-mode-listed-pages', 'listed_pages');
    $page->fillField('edit-facebook-pixel-visibility-request-path-pages', '/test-page');
    $page->pressButton('edit-submit');

    // See if script will load for our authenticated user:
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // By default, anonymous users should have the same result.
    $this->drupalLogout();
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if script is not loaded on a specific path, written without a slash.
   */
  public function testExclusionByPathWithoutSlashUrl() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Don't track on test-page:
    $page->fillField('edit-facebook-pixel-visibility-request-path-mode-all-pages', 'all_pages');
    $page->fillField('edit-facebook-pixel-visibility-request-path-pages', 'test-page');
    $page->pressButton('edit-submit');

    // See if script will load for our authenticated user, since the path
    // is written without the slash:
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // By default, anonymous users should have the same result:
    $this->drupalLogout();
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if script is loaded on a specific path written without a slash.
   */
  public function testInclusionByPathWithoutSlashUrl() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Check if tracked on test-page:
    $page->fillField('edit-facebook-pixel-visibility-request-path-mode-listed-pages', 'listed_pages');
    $page->fillField('edit-facebook-pixel-visibility-request-path-pages', 'test-page');
    $page->pressButton('edit-submit');

    // See if script will load for our authenticated user, since the path
    // is written without the slash:
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // By default, anonymous users should have the same result:
    $this->drupalLogout();
    $this->drupalGet('/test-page');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is not loaded on an authenticated user.
   */
  public function testExclusionByRoleAuthenticatedTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Track all roles except authenticated user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-all-roles', 'all_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-authenticated', 'authenticated');
    $page->pressButton('edit-submit');

    // See if script won't load for our authenticated user:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");

    // Anonymous users should still "see" the script:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is loaded on an authenticated user.
   */
  public function testInclusionByRoleAuthenticatedTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Only track authenticated user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-listed-roles', 'listed_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-authenticated', 'authenticated');
    $page->pressButton('edit-submit');

    // See if script will load for our authenticated user:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // Anonymous users shouldn't see the script:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is not loaded on an anonymous user.
   */
  public function testExclusionByRoleAnonymousTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Track all roles except anonymous user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-all-roles', 'all_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-anonymous', 'anonymous');
    $page->pressButton('edit-submit');

    // Logout and see if script won't load for a an anonymous user:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");

    // Authenticated users should still see the script:
    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is loaded on an anonymous user.
   */
  public function testInclusionByRoleAnonymousTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);

    // Only track anonymous user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-all-roles', 'listed_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-anonymous', 'anonymous');
    $page->pressButton('edit-submit');

    // Logout and see if script will load for an anonymous user:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // Authenticated users shouldn't see the script:
    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is not loaded on an admin user.
   */
  public function testExclusionByRoleAdminTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Track all roles except admin user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-all-roles', 'all_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-administrator', 'administrator');
    $page->pressButton('edit-submit');

    // Check if anonymous users still see the script:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // Check if authenticated users still see the script:
    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // Login as adminuser:
    $this->drupalLogout();
    $this->drupalLogin($this->adminuser);

    // Admin user shouldn't see the script:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is loaded on an admin user.
   */
  public function testInclusionByRoleAdminTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Login as adminuser:
    $this->drupalLogout();
    $this->drupalLogin($this->adminuser);
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Only track admin user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-listed-roles', 'listed_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-administrator', 'administrator');
    $page->pressButton('edit-submit');
    $this->drupalLogout();

    // Anonymous users shouldn't see the script:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");

    // Authenticated users should still see the script:
    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");

    // Login as adminuser:
    $this->drupalLogout();
    $this->drupalLogin($this->adminuser);

    // Admin user should see the script:
    $this->drupalGet('<front>');
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is not loaded on multiple users.
   */
  public function testExclusionByRoleMultipleRoleTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Track all roles except authenticated and anonymous user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-all-roles', 'all_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-authenticated', 'authenticated');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-anonymous', 'anonymous');
    $page->pressButton('edit-submit');

    // See if script won't load for our authenticated user:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");

    // Anonymous users also shouldn't see the script:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if the script is loaded on multiple users.
   */
  public function testInclusionByRoledMultipleRoleTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Only track authenticated and anonymous user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-listed-roles', 'listed_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-authenticated', 'authenticated');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-anonymous', 'anonymous');
    $page->pressButton('edit-submit');

    // See if script will load for our authenticated user:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // Anonymous users also should see the script:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

  /**
   * Tests if script is loaded on an admin user, inherited from authenticated.
   */
  public function testInclusionByRoleAuthenticatedInheritTest() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Login as adminuser:
    $this->drupalLogout();
    $this->drupalLogin($this->adminuser);
    // Set account key:
    $this->config('facebook_pixel.settings')->set('facebook_id', $this->fbPixelKey)->save();
    // Go to facebook_pixel settings page:
    $this->drupalGet('/admin/config/facebook_pixel');
    $session->statusCodeEquals(200);
    // Only track admin user:
    $page->fillField('edit-facebook-pixel-visibility-user-role-mode-listed-roles', 'listed_roles');
    $page->fillField('edit-facebook-pixel-visibility-user-role-roles-authenticated', 'authenticated');
    $page->pressButton('edit-submit');

    // Anonymous users shouldn't see the script:
    $this->drupalLogout();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', "script[src*='facebook_pixel.js']");

    // Authenticated users still see the script:
    $this->drupalLogin($this->user);
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");

    // Login as adminuser, shouldn't see the script:
    $this->drupalLogout();
    $this->drupalLogin($this->adminuser);
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', "script[src*='facebook_pixel.js']");
  }

}
