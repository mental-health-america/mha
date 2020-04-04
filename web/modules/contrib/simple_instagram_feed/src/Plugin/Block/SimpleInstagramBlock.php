<?php

namespace Drupal\simple_instagram_feed\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a block with a dynamic Instagram Feed.
 *
 * @Block(
 * id = "simple_instagram_block",
 * admin_label = @Translation("Simple Instagram Feed"),
 * )
 */
class SimpleInstagramBlock extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['simple_instagram_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instagram username'),
      '#description' => $this->t('Insert the username of the instagram account in the field above.'),
      '#default_value' => isset($config['simple_instagram_username']) ? $config['simple_instagram_username'] : 'instagram',
      '#required' => TRUE,
    ];

    $form['simple_instagram_display_profile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display profile?'),
      '#description' => $this->t('Do you wish to display the Instagram profile on this Instagram Feed?'),
      '#default_value' => isset($config['simple_instagram_display_profile']) ? $config['simple_instagram_display_profile'] : 'true',
    ];

    $form['simple_instagram_display_biography'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display bio?'),
      '#description' => $this->t('Do you wish to display the Instagram Bio on this Instagram Feed?'),
      '#default_value' => isset($config['simple_instagram_display_biography']) ? $config['simple_instagram_display_biography'] : 'true',
    ];

    $form['simple_instagram_items'] = [
      '#type' => 'textfield',
      '#size' => 3,
      '#maxlength' => 3,
      '#title' => $this->t('Number of images'),
      '#description' => $this->t('How many images do you wish to feature on this Instagram Feed?'),
      '#default_value' => isset($config['simple_instagram_items']) ? $config['simple_instagram_items'] : '12',
      '#required' => TRUE,
    ];

    $simple_items_range = range(1, 12);
    $form['simple_instagram_items_per_row'] = [
      '#type' => 'select',
      '#options' => [$simple_items_range],
      '#title' => $this->t('Number of images per row?'),
      '#description' => $this->t('How many images do you wish to feature on each row of this Instagram Feed? You can produce a single row if you set the numnber of images to equal the number of images per row.'),
      '#default_value' => isset($config['simple_instagram_items_per_row']) ? $config['simple_instagram_items_per_row'] : '5',
    ];

    $form['simple_instagram_styling'] = [
      '#type' => 'select',
      '#options' => ['true' => 'True', 'false' => 'False'],
      '#title' => $this->t('Styling'),
      '#description' => $this->t('Set to False to omit instagramFeed styles and provide your own in your CSS.'),
      '#default_value' => isset($config['simple_instagram_styling']) ? $config['simple_instagram_styling'] : 'true',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['simple_instagram_username'] = $values['simple_instagram_username'];
    $this->configuration['simple_instagram_display_profile'] = $values['simple_instagram_display_profile'];
    $this->configuration['simple_instagram_display_biography'] = $values['simple_instagram_display_biography'];
    $this->configuration['simple_instagram_items'] = $values['simple_instagram_items'];
    $this->configuration['simple_instagram_items_per_row'] = $values['simple_instagram_items_per_row'];
    $this->configuration['simple_instagram_styling'] = $values['simple_instagram_styling'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Return array.
    return [
      '#theme' => 'simple_instagram_block',
      '#markup' => $this->t('Simple Instagram Feed'),
      '#attached' => [
        'library' => [
          'simple_instagram_feed/simple_instagram_block',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
      ],
    ];
  }

}
