<?php

/**
 * @file
 */
namespace Drupal\form_donation_button\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_donation_button",
 * admin_label = @Translation("Form Donation Button"),
 * )
 */
class form_donation_button extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<style>
div#block-formdonationbutton {
    position: absolute;
    right: 0;
    top: -18px;
}
</style>
<div id="donation-form">
<a href="#XQUJNWQR" style="display: none"></a>
</div>
      '),
    );

  }
}

