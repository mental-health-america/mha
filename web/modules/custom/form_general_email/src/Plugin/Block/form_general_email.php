<?php

/**
 * @file
 */

namespace Drupal\form_general_email\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_general_email",
 * admin_label = @Translation("Form General Email Sign up"),
 * )
 */
class form_general_email extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('
      <div id="xXPxbAInLU">
        <script type="text/javascript" src="https://default.salsalabs.org/api/widget/template/6a21d121-b5f3-4486-bd56-e8efe1b95ba5/?tId=xXPxbAInLU" ></script>
      </div>
      '),
    ];

  }

}
