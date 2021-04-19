<?php

/**
 * @file
 */

namespace Drupal\form_annual_conference_email\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_annual_conference_email",
 * admin_label = @Translation("Form Annual Conference Email Sign up"),
 * )
 */
class form_annual_conference_email extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('
<div id="UVXlmRczSN">
    <script type="text/javascript" src="https://default.salsalabs.org/api/widget/template/9aa6a5eb-1ab6-4cc2-bf8c-bb9ccbaa5af6/?tId=UVXlmRczSN" ></script>
</div>
      '),
    ];

  }

}


