<?php

/**
 * @file
 */

namespace Drupal\form_workplace_mental_health_email\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_workplace_mental_health_email",
 * admin_label = @Translation("Form Workplace Mental Health Email Sign up"),
 * )
 */
class form_workplace_mental_health_email extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('
<div id="HGXxLPNheU">
    <script type="text/javascript" src="https://default.salsalabs.org/api/widget/template/91c8b17f-cf9a-4d1b-8640-036decdbf4b2/?tId=HGXxLPNheU" ></script>
</div>
      '),
    ];
  }
}


