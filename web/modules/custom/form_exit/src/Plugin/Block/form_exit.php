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
#form_exit a.btn.btn-danger.ext {
    background: red !important;
    color: white !important;
    text-decoration: none !important;
    font-size: 1.25rem;
    position: absolute;
    top: 100px;
    right: 0;
    border-radius: 0;
    padding: 0.5rem 1rem;
    text-transform: capitalize;
}
</style>
<div id="form_exit">
<a href="https://www.google.com" class="btn btn-danger" target="_self">Exit X</a>
</div>

      '),
    );

  }
}

