<?php

/**
 * @file
 */
namespace Drupal\form_donate_hope_help\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_donate_hope_help",
 * admin_label = @Translation("Form Donate"),
 * )
 */
class form_donate_hope_help extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<a href="#XRTWEYXW" style="display: none"></a>
      '),
    );

  }
}

