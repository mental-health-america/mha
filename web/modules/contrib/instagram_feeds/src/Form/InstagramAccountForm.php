<?php

namespace Drupal\instagram_feeds\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the InstagramAccount add/edit form.
 */
class InstagramAccountForm extends ContentEntityForm {

  /**
   * The instagram_feeds.settings configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $instagram_feeds_config;

  /**
   * Gets data from the instagram_feeds.settings configuration object.
   *
   * @param string $key
   *   A string that maps to a key within the configuration data.
   *
   * @return mixed
   *   The data that was requested.
   */
  protected function getConfig($key = '') {
    if (!isset($this->config)) {
      $this->instagram_feeds_config = $this->config('instagram_feeds.settings');
    }
    return $this->instagram_feeds_config->get($key);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if (empty($this->getConfig('client_id')) || empty($this->getConfig('client_secret'))) {
      return [
        'configure' => [
          '#type' => 'link',
          '#title' => $this->t('Configure Instagram Feeds'),
          '#access' => $this->currentUser()->hasPermission('administer instagram_feeds'),
          '#attributes' => [
            'class' => ['button', 'button--primary'],
          ],
          '#url' => Url::fromRoute('instagram_feeds.settings')->setOption('query', \Drupal::destination()->getAsArray()),
        ],
      ];
    }

    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $i_account */
    $i_account = $this->getEntity();

//    if ($i_account->isNew() && $i_account->get('token')->isEmpty()) {
//      return $this->getAuthWindowButton();
//    }
//    elseif (!$i_account->tokenIsValid()) {
    if (!$i_account->tokenIsValid()) {
      return $this->getAuthWindowButton() + parent::actions($form, $form_state);
    }

    return parent::actions($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if (empty($this->getConfig('client_id')) || empty($this->getConfig('client_secret'))) {
      return $form;
    }
    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $i_account */
    $i_account = $this->getEntity();
    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit @type</em> @title', [
        '@type' => $i_account->getEntityType()->getLabel(),
        '@title' => $i_account->label()
      ]);
    }
    $form = parent::form($form, $form_state);
    // TODO: Disable / hide fields with data from Instagram.
    $form['status']['#group'] = 'footer';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $i_account */
    $i_account = $this->getEntity();
    // Obtain Instagram short-lived and exchange to long-lived token.
    $code = $this->getRequest()->query->get('code');
    if ($i_account->isNew() && isset($code) && !empty($code)) {
      if ($i_account->get('token')->isEmpty() || $i_account->get('token_expiration')->isEmpty()) {
        $i_account->getToken($this->getConfig('client_id'), $this->getConfig('client_secret'), $code);
      }
      if ($i_account->get('account')->isEmpty() && $i_account->tokenIsValid()) {
        $i_account->getAccountName();
      }
    }
  }

  /**
   * Gets link to Instagram Auth Window.
   *
   * @return array
   *   Form link element renderable array.
   */
  protected function getAuthWindowButton(): array {
    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $i_account */
    $i_account = $this->getEntity();
    return [
      'auth' => [
        '#type' => 'link',
        '#title' => $this->t('Authenticate Instagram App'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
        '#url' => $i_account->getOauthUrl($this->getConfig('client_id')),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $instagram_account = $this->entity;
    $status = $instagram_account->save();
    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The %label Instagram Account created.', [
        '%label' => $instagram_account->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label Instagram Account updated.', [
        '%label' => $instagram_account->label(),
      ]));
    }
    $form_state->setRedirect('entity.instagram_account.collection');
    return $status;
  }

}
