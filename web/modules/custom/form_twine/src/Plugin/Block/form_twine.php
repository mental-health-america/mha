<?php

/**
 * @file
 */
namespace Drupal\form_twine\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_twine",
 * admin_label = @Translation("Form Twine"),
 * )
 */
class form_twine extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<div id="content">
    <script id="twine-script" src="//apps.twinesocial.com/embed?app=MentalHealthAm&amp;showNav=yes">&lt;a href="http://www.twinesocial.com/blog/pinterest-plugin-for-websites/" id="twine-hub-url"&gt;pinterest plugin for website&lt;/a&gt;</script>
</div>
      '),
    );

  }
}

