<?php

/**
 * @file
 */
namespace Drupal\mhm_fundraising_form\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_mhm_fundraising_form",
 * admin_label = @Translation("MHM Fundraise Form"),
 * )
 */
class mhm_fundraising_form extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<div id="mhm_fundraising_form">
<a href="#XRGKQKRD" style="display: none"></a>
</div>
      '),
    );

  }
}

