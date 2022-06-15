<?php

namespace Drupal\Tests\fraction\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\fraction\Fraction;
use Drupal\Tests\BrowserTestBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Tests the creation of fraction fields.
 *
 * @group Fraction
 */
class FractionFieldTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'entity_test', 'field_ui', 'fraction'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer node form display',
      'bypass node access',
      'administer entity_test fields',
      'access administration pages',
    ]));
  }

  /**
   * Test decimal widget field.
   */
  public function testFractionWidgetDecimal() {
    $max = 100;
    $min = 10;
    // Create a field with settings to validate.
    $field_name = mb_strtolower($this->randomMachineName());
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'fraction',
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' =>
        [
          'max' => $max,
          'min' => $min,
        ],
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction_decimal'])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction_decimal'])
      ->save();

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertFieldByName("{$field_name}[0][decimal]", '', 'Widget is displayed');

    // Submit decimal value.
    $value = '14.5678';
    $edit = [
      "{$field_name}[0][decimal]" => $value,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertText($this->t('entity_test @id has been created.', ['@id' => $id]), 'Entity was created');
    $this->assertRaw($value, 'Value is displayed.');

    // Try to create entries with more than one decimal separator; assert fail.
    $wrong_entries = [
      '3.14.159',
      '0..45469',
      '..4589',
      '6.459.52',
      '6.3..25',
    ];

    foreach ($wrong_entries as $wrong_entry) {
      $this->drupalGet('entity_test/add');
      $edit = [
        "{$field_name}[0][decimal]" => $wrong_entry,
      ];
      $this->drupalPostForm(NULL, $edit, $this->t('Save'));
      $this->assertRaw($this->t('%name must be a number.', ['%name' => $field_name]), 'Correctly failed to save value with more than one decimal point.');
    }

    // Try to create entries with minus sign not in the first position.
    $wrong_entries = [
      '3-3',
      '4-',
      '1.3-',
      '1.2-4',
      '-10-10',
    ];

    foreach ($wrong_entries as $wrong_entry) {
      $this->drupalGet('entity_test/add');
      $edit = [
        "{$field_name}[0][decimal]" => $wrong_entry,
      ];
      $this->drupalPostForm(NULL, $edit, $this->t('Save'));
      $this->assertRaw($this->t('%name must be a number.', ['%name' => $field_name]), 'Correctly failed to save value with minus sign in the wrong position.');
    }

    // Try to set a value above the maximum value.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][decimal]" => $max + 0.123,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('%name: the value may be no greater than %maximum.', ['%name' => $field_name, '%maximum' => $max]), 'Correctly failed to save value greater than maximum allowed value.');

    // Try to set a value below the minimum value.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][decimal]" => $min - 0.123,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('%name: the value may be no less than %minimum.', ['%name' => $field_name, '%minimum' => $min]), 'Correctly failed to save value less than minimum allowed value.');

    // Test the fraction decimal element limits.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][decimal]" => 10.1234567891,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('The maximum number of digits after the decimal place is 9.'));
  }

  /**
   * Test decimal widget field with negative values.
   */
  public function testFractionWidgetDecimalNegative() {
    // Create the test field.
    $field_name = mb_strtolower($this->randomMachineName());
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'fraction',
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction_decimal'])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction_decimal'])
      ->save();

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertFieldByName("{$field_name}[0][decimal]", '', 'Widget is displayed');

    // Submit negative decimal value.
    $value = '-14.5678';
    $edit = [
      "{$field_name}[0][decimal]" => $value,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertText($this->t('entity_test @id has been created.', ['@id' => $id]), 'Entity was created');
    $this->assertRaw($value, 'Value is displayed.');
  }

  /**
   * Test decimal widget field.
   */
  public function testFractionWidgetFraction() {
    $max = 100;
    $min = 10;
    // Create a field with settings to validate.
    $field_name = mb_strtolower($this->randomMachineName());
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'fraction',
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' =>
        [
          'max' => $max,
          'min' => $min,
        ],
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction'])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction'])
      ->save();

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertFieldByName("{$field_name}[0][fraction][numerator]", '', 'Numerator is displayed');
    $this->assertFieldByName("{$field_name}[0][fraction][denominator]", '', 'Denominator is displayed');

    // Submit fraction value.
    $edit = [
      "{$field_name}[0][fraction][numerator]" => 150,
      "{$field_name}[0][fraction][denominator]" => 10,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertText($this->t('entity_test @id has been created.', ['@id' => $id]), 'Entity was created');
    $this->assertRaw('150', 'Numerator is displayed.');
    $this->assertRaw('10', 'Denominator is displayed.');

    // Try to set a value above the maximum value.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][fraction][numerator]" => 15000,
      "{$field_name}[0][fraction][denominator]" => 10,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('%name: the value may be no greater than %maximum.', ['%name' => $field_name, '%maximum' => $max]), 'Correctly failed to save value greater than maximum allowed value.');

    // Try to set a value below the minimum value.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][fraction][numerator]" => 1,
      "{$field_name}[0][fraction][denominator]" => 10,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('%name: the value may be no less than %minimum.', ['%name' => $field_name, '%minimum' => $min]), 'Correctly failed to save value less than minimum allowed value.');

    // Empty denominator.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][fraction][numerator]" => 1,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('The denominator of a fraction cannot be zero or empty (if a numerator is provided).'));

    // Numerators must be between -9223372036854775808 and 9223372036854775807.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][fraction][numerator]" => '-9223372036854775809',
      "{$field_name}[0][fraction][denominator]" => 10,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('The numerator of a fraction must be between -9223372036854775808 and 9223372036854775807.'));
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][fraction][numerator]" => '9223372036854775808',
      "{$field_name}[0][fraction][denominator]" => 10,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('The numerator of a fraction must be between -9223372036854775808 and 9223372036854775807.'));

    // Denominators must be between 0 and 2147483647.
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][fraction][numerator]" => 10,
      "{$field_name}[0][fraction][denominator]" => -1,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('The denominator of a fraction must be greater than 0 and less than 2147483647.'));
    $this->drupalGet('entity_test/add');
    $edit = [
      "{$field_name}[0][fraction][numerator]" => 10,
      "{$field_name}[0][fraction][denominator]" => 2147483648,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    $this->assertRaw($this->t('The denominator of a fraction must be greater than 0 and less than 2147483647.'));
  }

  /**
   * Test min/max with decimal values.
   */
  public function testFractionWidgetMinMaxDecimal() {
    $this->drupalCreateContentType(['type' => 'article']);
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->drupalGet('/admin/structure/types/manage/article/fields/add-field');
    $page->selectFieldOption('new_storage_type', 'fraction');
    $page->fillField('label', 'Fraction field');
    $page->fillField('field_name', 'fraction_field');
    $page->pressButton('Save and continue');
    $page->pressButton('Save field settings');
    $page->fillField('settings[min]', 10.5);
    $page->fillField('settings[max]', 100.5);
    $page->pressButton('Save settings');
    $assert_session->pageTextContains('Saved Fraction field configuration.');
  }

  /**
   * Tests that editing an empty fraction doesn't show a 0.
   *
   * @see https://www.drupal.org/project/fraction/issues/3096236
   */
  public function testFractionWidgetDecimalEditNull() {
    // Create a field with settings to validate.
    $field_name = mb_strtolower($this->randomMachineName());
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'fraction',
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction_decimal'])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, ['type' => 'fraction_decimal'])
      ->save();

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertFieldByName("{$field_name}[0][decimal]", '', 'Widget is displayed');

    // Submit decimal value.
    $value = '1.234';
    $edit = [
      "{$field_name}[0][decimal]" => $value,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Save'));
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertText($this->t('entity_test @id has been created.', ['@id' => $id]), 'Entity was created');
    $this->assertRaw($value, 'Value is displayed.');

    // Empty the field.
    $edit = [
      "{$field_name}[0][decimal]" => NULL,
    ];
    $this->drupalPostForm("entity_test/manage/$id/edit", $edit, $this->t('Save'));
    $this->assertNoRaw($value, 'Value is removed.');

    // The field should have no value (not 0, just empty).
    $this->drupalGet("entity_test/manage/$id/edit");
    $elements = $this->xpath($this->constructFieldXpath('name', "{$field_name}[0][decimal]"));
    $element = reset($elements);
    $this->assertIdentical($element->getValue(), '', 'Field is empty');
  }

}
