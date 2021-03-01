<?php

/**
 * @file
 */
namespace Drupal\form_footer_email\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_footer_email",
 * admin_label = @Translation("Form Email Sign up"),
 * )
 */
class form_footer_email extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
      <div id="gNHKcuBICg">
    <script type="text/javascript" src="https://default.salsalabs.org/api/widget/template/475d7d0f-2f04-4142-b9e4-50c6abce55ea/?tId=gNHKcuBICg" ></script>
</div>
      '),
    );

  }
}
