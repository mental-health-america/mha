<?php

namespace Drupal\webform_validation\Validate;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformArrayHelper;
use Drupal\webform\Utility\WebformElementHelper;

/**
 * Form API callback. Validate element value.
 */
class WebformValidateConstraint {

  /**
   * Array of types that are supported by this module.
   */
  const ALLOWED_TYPES = [
    'date',
    'datetime',
    'email',
    'hidden',
    'number',
    'select',
    'tel',
    'textarea',
    'textfield',
    'webform_document_file',
    'webform_entity_checkboxes',
    'webform_signature',
    'webform_time',
  ];

  /**
   * Array of types that are supported by this module for comparisons.
   */
  const ALLOWED_TYPES_COMPARE = [
    'date',
    'datetime',
    'number',
    'webform_time',
  ];

  /**
   * Validates Backend fields.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function validateBackendComponents(array $form, FormStateInterface &$formState): void {
    $values = $formState->cleanValues()->getValues();

    // Track if updates to properties have been made.
    $update = FALSE;

    // Remove unchecked items from arrays of selected components. This prevents
    // the saved arrays from having "key => 0" for unchecked components.
    foreach (['equal__components', 'some_of_several__components'] as $component) {
      if (isset($values['properties'][$component])) {
        $values['properties'][$component] = array_filter($values['properties'][$component]);
        $update = TRUE;
      }
    }

    // Validate configuration of 'equal' validator.
    if (!empty($values['properties']['equal__enabled'])) {
      if (!$values['properties']['equal__components']) {
        $formState->setErrorByName('properties][equal__components', 'Please select at least 1 Equal components.');
      }
    }

    // Validate configuration of 'some_of_several' validator.
    if (!empty($values['properties']['some_of_several__enabled'])) {
      if (count($values['properties']['some_of_several__components']) < 1) {
        $formState->setErrorByName('properties][some_of_several__components', 'You need to select at least 1 component.');
      }
      if (empty($values['properties']['some_of_several__completed'])) {
        $formState->setErrorByName('properties][some_of_several__completed', 'You need to specify the number to be completed.');
      }
      elseif ($parse = static::parseSomeOfSeveralCompleted($values['properties']['some_of_several__completed'])) {
        // The some_of_several__completed value is valid. Remove extra
        // whitespace and leading zeros if any.
        $correct_value = $parse['operator'] . $parse['number'];
        if ($values['properties']['some_of_several__completed'] !== $correct_value) {
          $values['properties']['some_of_several__completed'] = $correct_value;
          $update = TRUE;
        }
      }
      else {
        // Invalid value in some_of_several__completed.
        $formState->setErrorByName('properties][some_of_several__completed', 'Invalid value.');
      }
    }

    // Validate configuration of 'compare' validator.
    if (!empty($values['properties']['compare__enabled'])) {
      if (empty($values['properties']['compare__component'])) {
        $formState->setErrorByName('properties][compare__component', 'Please select compare components.');
      }
      if (empty($values['properties']['compare__operator'])) {
        $formState->setErrorByName('properties][compare__operator', 'Please select compare operator.');
      }
    }
    // Prevent configuration from being saved when disabled.
    else {
      foreach (['compare__component', 'compare__operator'] as $component) {
        unset($values['properties'][$component]);
      }
      $update = TRUE;
    }

    // Update properties if any have changed.
    if ($update) {
      $formState->setValue('properties', $values['properties']);
    }
  }

  /**
   * Validates form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function validate(array &$form, FormStateInterface $formState): void {
    self::validateElements($form['elements'], $form, $formState);
  }

  /**
   * Validates element.
   *
   * @param array $elements
   *   The form elements.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  private static function validateElements(array $elements, array &$form, FormStateInterface $formState): void {
    foreach ($elements as $keyElement => &$keyValue) {
      if (!WebformElementHelper::isElement($keyValue, $keyElement)) {
        continue;
      }
      if (!empty($keyValue['#equal__enabled'])) {
        self::validateFrontEqualComponent($keyValue, $formState, $form);
      }
      if (!empty($keyValue['#compare__enabled'])) {
        self::validateFrontCompareComponent($keyValue, $formState, $form);
      }
      if (!empty($keyValue['#some_of_several__enabled'])) {
        self::validateFrontSomeSeveralComponent($keyValue, $formState, $form);
      }
      self::validateElements($keyValue, $form, $formState);
    }
  }

  /**
   * Validates Equal components on front end.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $form
   *   The form array.
   */
  public static function validateFrontEqualComponent(array &$element, FormStateInterface $formState, array &$form): void {
    $webformKey = $element['#webform_key'];
    $equalComponents = $element['#equal__components'];
    $thisValue = is_array($formState->getValue($webformKey)) ? $formState->getValue($webformKey) : [$formState->getValue($webformKey)];
    $submittedValues = $formState->cleanValues()->getValues();
    $storage = $formState->getStorage();
    $visitedElements = !empty($storage['visited']) ? $storage['visited'] : [];
    $error = FALSE;
    foreach ($equalComponents as $key => $value) {
      // Equal component key.
      if (isset($form['elements'][$key])) {
        $fieldElement = $form['elements'][$key];
        $fieldElement['access'] = !empty($form['elements'][$key]['#access']);
        $fieldElement['multiple'] = !empty($form['elements'][$key]['#webform_multiple']);
      }
      elseif (!isset($form['elements'][$key])) {
        $found = FALSE;
        $fieldElement = [];
        self::getFormElementAccess($form['elements'], $key, $found, $fieldElement);
      }
      if (!empty($fieldElement['access']) && !in_array($key, $visitedElements)) {
        $visitedElements[] = $key;
      }
      if (!empty($element['#access']) && !in_array($webformKey, $visitedElements)) {
        $visitedElements[] = $webformKey;
      }
      // Equal component validation.
      if (!empty($thisValue[0]) && !empty($submittedValues[$key]) && array_key_exists($key, $submittedValues) && in_array($key, $visitedElements) && in_array($webformKey, $visitedElements)) {
        // Many to Many.
        if (!empty($fieldElement['multiple']) && $element['#webform_multiple'] == TRUE) {
          $result = array_intersect($thisValue, $submittedValues[$key]);
          if (empty($result) && !empty($submittedValues[$key])) {
            $error = TRUE;
            break;
          }
        }
        // One to Many.
        elseif (!empty($fieldElement['multiple'])) {
          if (!in_array($thisValue[0], $submittedValues[$key])) {
            $error = TRUE;
            break;
          }
        }
        // Many to One.
        elseif (!in_array($submittedValues[$key], $thisValue) && !empty($element['#webform_multiple'])) {
          $error = TRUE;
          break;
        }
        // One to One.
        elseif (!in_array($submittedValues[$key], $thisValue) && empty($element['#webform_multiple']) && empty($fieldElement['multiple'])) {
          $error = TRUE;
          break;
        }
      }
      elseif ($value != '0' && !empty($thisValue[0]) && in_array($key, $visitedElements) && empty($submittedValues[$key])) {
        $error = TRUE;
        break;
      }
      elseif ($value != '0' && empty($thisValue[0]) && !empty($submittedValues[$key]) && in_array($webformKey, $visitedElements)) {
        $error = TRUE;
        break;
      }
    }
    $storage['visited'] = $visitedElements;
    $formState->setStorage($storage);
    if ($error) {
      if (empty($fieldElement['access']) || (!empty($element['#access']) && empty($thisValue[0]))) {
        $fieldElement = $element;
        $content = "should not be Empty";
      }
      if (isset($fieldElement['#title'])) {
        $source_name = empty($element['#title']) ? $element['#parents'][0] : $element['#title'];
        $tArgs = [
          '%name' => empty($fieldElement['#title']) ? $fieldElement['#parents'][0] : $fieldElement['#title'],
          '%value' => $value,
          '%content' => !empty($content) ? $content : 'does not match',
          '%sourceName' => empty($content) ? $source_name : '',
        ];
        $formState->setError(
            $fieldElement,
            t('%name %content %sourceName', $tArgs)
        );
      }
      else {
        $formState->setError($element);
      }
    }
  }

  /**
   * Validates compare fields.
   *
   * @param array $element
   *   The form elements.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $form
   *   The form array.
   */
  public static function validateFrontCompareComponent(array &$element, FormStateInterface $formState, array &$form): void {
    $webformKey = $element['#webform_key'];
    $compareWithField = $element['#compare__component'];
    $compareOperator = $element['#compare__operator'];
    $compareErrorMsg = $element['#compare__custom_error'] ?? NULL;

    $thisValue = is_array($formState->getValue($webformKey)) ? $formState->getValue($webformKey) : [$formState->getValue($webformKey)];
    $submittedValues = $formState->cleanValues()->getValues();
    $compareWithValue = is_array($submittedValues[$compareWithField]) ? $submittedValues[$compareWithField] : [$submittedValues[$compareWithField]];
    $storage = $formState->getStorage();
    $visitedElements = !empty($storage['visited']) ? $storage['visited'] : [];
    $found = FALSE;
    $fieldElement = [];
    self::getFormElementAccess($form['elements'], $compareWithField, $found, $fieldElement);
    if (!empty($fieldElement['access']) && !in_array($compareWithField, $visitedElements)) {
      $visitedElements[] = $compareWithField;
    }
    if (!empty($element['#access']) && !in_array($webformKey, $visitedElements)) {
      $visitedElements[] = $webformKey;
    }
    $error = FALSE;
    // Compare the values if both elements in the comparison have a value that
    // is not the empty string.
    if (((string) $compareWithValue[0] ?? NULL) && ((string) $thisValue[0] ?? NULL) && in_array($compareWithField, $visitedElements) && in_array($webformKey, $visitedElements)) {
      switch ($compareOperator) {
        case '>':
          if (!(min($compareWithValue) > max($thisValue))) {
            $error = TRUE;
            $compareOperatorName = t('greater than');
          }
          break;

        case '>=':
          if (!(min($compareWithValue) >= max($thisValue))) {
            $error = TRUE;
            $compareOperatorName = t('greater than or equal to');
          }
          break;

        case '<':
          if (!(max($compareWithValue) < min($thisValue))) {
            $error = TRUE;
            $compareOperatorName = t('less than');
          }
          break;

        case '<=':
          if (!(max($compareWithValue) <= min($thisValue))) {
            $error = TRUE;
            $compareOperatorName = t('less than or equal to');
          }
          break;

        default:
          \Drupal::logger('webform_validation')->error('The compare validator on element @element has an invalid operator.', ['@element' => $element['#webform_key']]);
      }
    }
    $storage['visited'] = $visitedElements;
    $formState->setStorage($storage);
    if ($error) {
      if (empty($fieldElement['access']) || (!empty($element['#access']) && empty($thisValue[0]))) {
        $fieldElement = $element;
      }
      if (isset($fieldElement['#title'])) {
        // Generate default error message.
        if (!$compareErrorMsg) {
          $args = [
            '@compareOperator' => $compareOperatorName,
            '@compareElement' => $element['#title'],
          ];
          $compareErrorMsg = t('This element must be @compareOperator @compareElement.', $args);
        }

        $formState->setError($fieldElement, $compareErrorMsg);
      }
      else {
        $formState->setError($fieldElement);
      }
    }
  }

  /**
   * Parse a #some_of_several__completed property.
   *
   * @param string $some_of_several__completed
   *   The #some_of_several__completed property to parse.
   *
   * @return array|null
   *   An array with keys 'operator' and 'number' or NULL if invalid.
   */
  protected static function parseSomeOfSeveralCompleted(string $some_of_several__completed): ?array {
    $some_of_several__completed = trim($some_of_several__completed);
    if (preg_match('/^(=|<=|>=)\s*(\d+)$/', $some_of_several__completed, $matches)) {
      return [
        'operator' => $matches[1],
        'number' => (int) $matches[2],
      ];
    }
    return NULL;
  }

  /**
   * Validates Some of Several in fields.
   *
   * @param array $element
   *   The form elements.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $form
   *   The form array.
   */
  public static function validateFrontSomeSeveralComponent(array &$element, FormStateInterface $formState, array &$form): void {
    $flatValues = array_filter($formState->cleanValues()->getValues());
    $formObject = $formState->getFormObject();
    $webform = $formObject->getWebform();
    $storage = $formState->getStorage();
    $webformKey = $element['#webform_key'];
    $sosComponents = array_filter($element['#some_of_several__components']);

    $some_of_several__completed = static::parseSomeOfSeveralCompleted($element['#some_of_several__completed']);
    // Skip validation and log if not properly configured.
    if (!$some_of_several__completed) {
      \Drupal::logger('webform_validation')->error('The some_of_several validator on element @element has an invalid "Number to be completed".', ['@element' => $element['#webform_key']]);
      return;
    }

    $performValidation = TRUE;
    if (!empty($element['#some_of_several__final_validation'])) {
      $pages = $webform->getPages();
      if (!empty($pages)) {
        $currentPage = $formState->get('current_page');
        $nextPage = WebformArrayHelper::getNextKey($pages, $currentPage);
        if ($nextPage != 'webform_confirmation') {
          $performValidation = FALSE;
        }
      }
    }
    unset($flatValues['submit']);
    $currentArray = [$webformKey => $webformKey];
    $sosComponentsRev = array_merge_recursive($sosComponents, $currentArray);
    $items = [];
    $itemsfound = 0;
    $visitedElements = !empty($storage['visited']) ? $storage['visited'] : [];
    foreach ($sosComponentsRev as $cid => $component) {
      if (in_array($cid, $visitedElements)) {
        if (!empty($flatValues[$cid])) {
          $items[$cid] = $flatValues[$cid];
        }
        $itemsfound++;
      }
      else {
        $found = $flag = FALSE;
        $fieldElement = [];
        self::getFormElementAccess($form['elements'], $cid, $found, $fieldElement, $flag);
        if ((!empty($fieldElement['access']))) {
          if (!empty($flatValues[$cid])) {
            $items[$cid] = $flatValues[$cid];
          }
          $visitedElements[] = $cid;
          $itemsfound++;
        }
      }
    }

    $storage['visited'] = $visitedElements;
    $formState->setStorage($storage);

    $compareNumber = $some_of_several__completed['number'];
    if ($compareNumber < 1) {
      $compareNumber = 1;
    }
    elseif ($compareNumber > count($sosComponentsRev)) {
      $compareNumber = count($sosComponentsRev);
    }
    $validationFlag = FALSE;
    if ($some_of_several__completed['operator'] === '=') {
      if (($itemsfound >= $compareNumber)) {
        $validationFlag = TRUE;
      }
    }
    elseif ($some_of_several__completed['operator'] === '<=') {
      if (($itemsfound > 0)) {
        $validationFlag = TRUE;
      }
    }
    else {
      if (($itemsfound >= $compareNumber)) {
        $validationFlag = TRUE;
      }
    }
    if ($validationFlag &&  $performValidation) {
      $numberCompleted = count($items);
      $severalComponents = [];
      $elements = $webform->getElementsInitializedAndFlattened();
      foreach ($elements as $elementKey => &$elementComponent) {
        if (in_array($elementComponent['#webform_key'], $sosComponentsRev)) {
          $severalComponents[$elementKey] = $elementComponent['#admin_title'];
        }
      }
      // Parse the comparision operator and do comparison.
      $error = FALSE;
      if ($some_of_several__completed['operator'] === '=') {
        if (!($numberCompleted === $compareNumber)) {
          $verb = t('exactly');
          $error = TRUE;
        }
      }
      elseif ($some_of_several__completed['operator'] === '<=') {
        if (!($numberCompleted <= $compareNumber)) {
          $verb = t('at most');
          $error = TRUE;
        }
      }
      else {
        if (!($numberCompleted >= $compareNumber)) {
          $verb = t('at least');
          $error = TRUE;
        }
      }
      if ($error) {
        $renderable = [
          '#theme' => 'item_list',
          '#items' => $severalComponents,
        ];
        $items = \Drupal::service('renderer')->render($renderable);
        $errorMessage = t('You must complete %verb %compare_number of these items: %items', [
          '%verb' => $verb,
          '%compare_number' => $compareNumber,
          '%items' => $items,
        ]);
        $formState->setError($element, $errorMessage);
      }
    }
  }

  /**
   * Check Element Access.
   *
   * @param array $elements
   *   The form elements.
   * @param string $searchKey
   *   Key of the equal component.
   * @param bool $found
   *   The key.
   * @param array $element
   *   Result array.
   */
  private static function getFormElementAccess(array &$elements, string $searchKey, bool &$found, array &$element): void {
    if (!$found) {
      $element['access'] = $element['multiple'] = FALSE;
      foreach ($elements as $keyElement => &$keyValue) {
        if (!WebformElementHelper::isElement($keyValue, $keyElement)) {
          continue;
        }
        if (!empty($keyElement) && $keyElement == $searchKey) {
          $found = TRUE;
          $element = $keyValue;
          if (!empty($keyValue['#access']) || !empty($keyValue['#visited'])) {
            $element['access'] = TRUE;
          }
          if (!empty($keyValue['#webform_multiple'])) {
            $element['multiple'] = TRUE;
          }
        }
        elseif (!$found) {
          self::getFormElementAccess($keyValue, $searchKey, $found, $element);
        }
      }
    }
  }

  /**
   * Go previous page.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function formSubmitPrevious(array &$form, FormStateInterface $formState): void {
    $storage = $formState->getStorage();
    $visitedElements = !empty($storage['visited']) ? $storage['visited'] : [];
    self::formElementUnset($form['elements'], $formState, $visitedElements);
    $storage['visited'] = $visitedElements;
    $formState->setStorage($storage);
  }

  /**
   * Removing the values in visited array while going back to previous page.
   *
   * @param array $elements
   *   The form elements.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param array $visitedElements
   *   Group of visited elements.
   */
  private static function formElementUnset(array &$elements, FormStateInterface $formState, array &$visitedElements): void {
    foreach ($elements as $key => &$value) {
      if (!WebformElementHelper::isElement($value, $key)) {
        continue;
      }
      if (!empty($value['#access'])) {
        if (($searchKey = array_search($key, $visitedElements)) !== FALSE) {
          array_splice($visitedElements, $searchKey, 1);
        }
      }
      self::formElementUnset($value, $formState, $visitedElements);
    }
  }

}
