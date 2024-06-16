<?php

/**
 * @file
 */
namespace Drupal\form_donation_mobile_button\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_mobile_donation_button",
 * admin_label = @Translation("Form Donation Mobile Button"),
 * )
 */
class form_donation_mobile_button extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<style>
div#block-formdonationmobilebutton {
}
</style>
<div id="donation-mobile-form">
<a href="#XQUJNWQR" style="display: none"></a>
</div>
      '),
    );

  }
}

