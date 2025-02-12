<?php

/**
 * @file
 */
namespace Drupal\form_fundraise\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_fundraise",
 * admin_label = @Translation("Form Fundraise"),
 * )
 */
class form_fundraise extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<div id="form_fundraise" class="col">
<a href="#XVDYSTPB" style="display: none"></a>
</div>
      '),
    );

  }
}

