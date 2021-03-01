<?php

/**
 * @file
 */

namespace Drupal\form_young_leaders_email\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_young_leaders_email",
 * admin_label = @Translation("Form Young Leaders Email Sign up"),
 * )
 */
class form_young_leaders_email extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('
<div id="pxFHhwLmAD">
    <script type="text/javascript" src="https://default.salsalabs.org/api/widget/template/121deac6-1ad4-4a0a-87ae-3eeba43ec56f/?tId=pxFHhwLmAD" ></script>
</div>
      '),
    ];
  }
}


