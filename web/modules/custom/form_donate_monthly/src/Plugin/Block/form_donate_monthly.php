<?php

/**
 * @file
 */
namespace Drupal\form_donate_monthly\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_donate_monthly",
 * admin_label = @Translation("Form Donate Monthly"),
 * )
 */
class form_donate_monthly extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<a href="#XGGVSEVE" style="display: none"></a>
      '),
    );

  }
}

