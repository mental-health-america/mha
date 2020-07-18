<?php

namespace Drupal\content_access\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Template\Attribute;
use Drupal\node\NodeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Node Access settings form.
 * @package Drupal\content_access\Form
 */
class ContentAccessPageForm extends FormBase {
  use ContentAccessRoleBasedFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_access_page';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $defaults = [];

    foreach (_content_access_get_operations() as $op => $label) {
      $defaults[$op] = content_access_per_node_setting($op, $node);
    }

    $this->roleBasedForm($form, $defaults, $node->getType());

    // Add an after_build handler that disables checkboxes, which are enforced by permissions.
    $build_info = $form_state->getBuildInfo();
    $build_info['files'][] = [
      'module' => 'content_access',
      'type' => 'inc',
      'name' => 'content_access.admin'
    ];
    $form_state->setBuildInfo($build_info);

    foreach (['update', 'update_own', 'delete', 'delete_own'] as $op) {
      $form['per_role'][$op]['#process'][] = '::forcePermissions';
    }

    // ACL form.
    if (\Drupal::moduleHandler()->moduleExists('acl')) {
      // This is disabled when there is no node passed.
      $form['acl'] = [
        '#type' => 'fieldset',
        '#title' => t('User access control lists'),
        '#description' => t('These settings allow you to grant access to specific users.'),
        '#collapsible' => TRUE,
        '#tree' => TRUE,
      ];

      foreach (['view', 'update', 'delete'] as $op) {
        $acl_id = content_access_get_acl_id($node, $op);

        $view = (int) ($op == 'view');
        $update = (int) ($op == 'update');
        acl_node_add_acl($node->id(), $acl_id, $view, $update, (int) ($op == 'delete'), content_access_get_settings('priority', $node->getType()));

        $form['acl'][$op] = acl_edit_form($form_state, $acl_id, t('Grant @op access', ['@op' => $op]));

        $post_acl_id = \Drupal::request()->request->get('acl_' . $acl_id, NULL);
        $form['acl'][$op]['#collapsed'] = !isset($post_acl_id) && !unserialize($form['acl'][$op]['user_list']['#default_value']);
      }
    }

    $storage['node'] = $node;
    $form_state->setStorage($storage);

    $form['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset to defaults'),
      '#weight' => 10,
      '#submit' => ['::pageResetSubmit'],
      '#access' => !empty(content_access_get_per_node_settings($node)),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 10,
    ];

    // @todo not true anymore?
    // http://drupal.org/update/modules/6/7#hook_node_access_records
    if (!$node->isPublished()) {
      $this->messenger()->addError($this->t("Warning: Your content is not published, so this settings are not taken into account as long as the content remains unpublished."));
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = [];
    $storage = $form_state->getStorage();
    $values = $form_state->getValues();
    $node = $storage['node'];

    foreach (_content_access_get_operations() as $op => $label) {
      // Set the settings so that further calls will return this settings.
      $filtered_values = array_filter($values[$op]);
      $settings[$op] = array_keys($filtered_values);
    }

    // Save per-node settings.
    content_access_save_per_node_settings($node, $settings);

    if (\Drupal::moduleHandler()->moduleExists('acl')) {
      foreach (array('view', 'update', 'delete') as $op) {
        $values = $form_state->getValues();
        acl_save_form($values['acl'][$op]);
        \Drupal::moduleHandler()->invokeAll('user_acl', $settings);
      }
    }

    // Apply new settings.
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->writeGrants($node);
    \Drupal::moduleHandler()->invokeAll('per_node', $settings);

    foreach (Cache::getBins() as $service_id => $cache_backend) {
      $cache_backend->deleteAll();
    }
//xxxx
// route: node.configure_rebuild_confirm:
// path:  '/admin/reports/status/rebuild'
    $this->messenger()->addMessage(t('Your changes have been saved. You may have to <a href=":rebuild">rebuild permisions</a> for your changes to take effect.',
      array(':rebuild' => Url::FromRoute('node.configure_rebuild_confirm')->ToString())));
  }

  /**
   * Submit callback for reset on content_access_page().
   */
  function pageResetSubmit(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    content_access_delete_per_node_settings($storage['node']);
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->writeGrants($storage['node']);

    $this->messenger()->addMessage(t('The permissions have been reset to the content type defaults.'));
  }


  /**
   * Formapi #process callback, that disables checkboxes for roles without access to content.
   */
  function forcePermissions($element, FormStateInterface $form_state, &$complete_form) {
    $storage = $form_state->getStorage();
    if (!empty($storage['node'] && is_array($element['#parents']))) {
      $node = $storage['node'];
      foreach (content_access_get_settings(reset($element['#parents']), $node->getType()) as $rid) {
        $element[$rid]['#disabled'] = TRUE;
        $element[$rid]['#attributes']['disabled'] = 'disabled';
        $element[$rid]['#value'] = TRUE;
        $element[$rid]['#checked'] = TRUE;

        $prefix_attr = new Attribute([
          'title' => t('Permission is granted due to the content type\'s access control settings.'),
        ]);
        $element[$rid]['#prefix'] = '<span ' . $prefix_attr . '>';
        $element[$rid]['#suffix'] = "</span>";
      }
    }
    return $element;
  }
}
