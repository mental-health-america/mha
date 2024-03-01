<?php

/**
 * @file
 */
namespace Drupal\mha_donation\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_mha_donation",
 * admin_label = @Translation("MHA Donation"),
 * )
 */
class mha_donation extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<div id="donation-form-mha">
<a href="#XNFZSHWU" style="display: none"></a>
</div>
      '),
    );

  }
}

