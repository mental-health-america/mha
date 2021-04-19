<?php

/**
 * @file
 */
namespace Drupal\form_donate\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_donate",
 * admin_label = @Translation("Form Donate"),
 * )
 */
class form_donate extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<div id="RDaZcgrJCp">
    <script type="text/javascript" src="https://default.salsalabs.org/api/widget/template/5b26eb37-505d-436a-90c0-8e5276379511/?tId=RDaZcgrJCp" ></script>
</div>
      '),
    );

  }
}
