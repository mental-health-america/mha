<?php

namespace Drupal\advanced_email_validation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\advanced_email_validation\Helper\EmailValidatorHelper;

/**
 * Configure Email Validation settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advanced_email_validation_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['advanced_email_validation.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $checks = EmailValidatorHelper::getValidationChecks();
    $config = $this->config('advanced_email_validation.settings');
    $options = [];
    $weight = 1;

    foreach ($checks as $key => $check) {
      $form[$key] = [
        '#type' => 'fieldset',
        '#title' => $check['settings_title'],
        '#weight' => $weight,
        '#states' => [
          'visible' => [
            ':input[name="rules[' . $key . ']"]' => ['checked' => TRUE],
          ],
        ],
        $key . '_error_message' => [
          '#type' => 'textfield',
          '#title' => $this->t('Error message'),
          '#description' => $check['error_description'] ?? NULL,
          '#size' => 120,
          '#default_value' => $config->get('error_messages.' . $key) ?? $check['default_error_message'],
          '#states' => [
            'required' => [
              ':input[name="rules[' . $key . ']"]' => ['checked' => TRUE],
            ],
          ],
        ],
      ];

      if (array_key_exists('domain_list', $check)) {
        $form[$key][$key . '_domain_list'] = [
          '#type' => 'textarea',
          '#title' => $check['domain_list']['title'],
          '#description' => $check['domain_list']['description'],
          '#default_value' => implode("\r\n", $config->get('domain_lists.' . $key) ?? []),
          '#placeholder' => 'example.org',
        ];
      }

      $weight++;

      if (array_key_exists('rule_title', $check)) {
        $options[$key] = $check['rule_title'];
      }
    }

    $form['rules'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Ensure user account email <em>domains</em> are:'),
      '#options' => $options,
      '#default_value' => $config->get('rules') ?? [],
      // We want this at the top of the form, but we're making use of the
      // preceding loop to build our options.
      '#weight' => 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $checks = EmailValidatorHelper::getValidationChecks();
    foreach (array_keys($checks) as $key) {
      $element = $key . '_domain_list';
      $value = $form_state->getValue($element);
      if ($value && preg_match('/@+/', $value)) {
        $form_state->setErrorByName($element, $this->t('Use domain names only (the part after "@" in an email address)'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $checks = EmailValidatorHelper::getValidationChecks();
    $config = $this->config('advanced_email_validation.settings');

    $config->set('rules', $form_state->getValue('rules'));

    foreach ($checks as $key => $check) {
      $errorMessage = $form_state->getValue($key . '_error_message');
      $config->set('error_messages.' . $key, $errorMessage);

      if (array_key_exists('domain_list', $check)) {
        $domainList = explode("\r\n", $form_state->getValue($key . '_domain_list'));
        foreach ($domainList as &$domain) {
          $domain = trim($domain);
        }
        $config->set('domain_lists.' . $key, $domainList);
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
