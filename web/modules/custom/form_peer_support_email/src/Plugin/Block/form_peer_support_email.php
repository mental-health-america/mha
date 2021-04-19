<?php

/**
 * @file
 */

namespace Drupal\form_peer_support_email\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_peer_support_email",
 * admin_label = @Translation("Form Peer Support Email Sign up"),
 * )
 */
class form_peer_support_email extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('
<div class="p-5">
  <div id="hFwZMqrqDw">
    <script type="text/javascript" src="https://default.salsalabs.org/api/widget/template/08ac95d1-0980-430e-a5f3-3350eaec4285/?tId=hFwZMqrqDw" ></script>
  </div>
 </div>
      '),
    ];

  }

}


