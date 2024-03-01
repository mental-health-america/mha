<?php

/**
 * @file
 */
namespace Drupal\form_donation\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_donation",
 * admin_label = @Translation("Form donation"),
 * )
 */
class form_donation extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<div id="donation-form">
<a href="#XNFZSHWU" style="display: none"></a>
</div>
      '),
    );

  }
}

