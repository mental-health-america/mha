<?php

/**
 * @file
 */
namespace Drupal\form_exit\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_exit",
 * admin_label = @Translation("Form Exit"),
 * )
 */
class form_exit extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<style>
a.exit.red {
    background: red !important;
    color: white !important;
    text-decoration: none !important;
    font-size: 1.25rem;
    font-weight: bold !important;
    position: fixed;
    top: 100px;
    right: 0;
    border-radius: 0;
    padding: 0.5rem 1rem;
    text-transform: capitalize;
    z-index: 9999999999999!important;
}
</style>
<a href="https://www.google.com" class="exit red" target="_self">Exit X</a>
      '),
    );

  }
}

