<?php

/**
 * @file
 */
namespace Drupal\form_donate_2024\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_donate_2024",
 * admin_label = @Translation("Form Donate 2024"),
 * )
 */
class form_donate_2024 extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<a href="#XUGGTNMK" style="display: none"></a>
      '),
    );

  }
}

