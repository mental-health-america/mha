<?php

/**
 * @file
 */
namespace Drupal\form_crypto\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Custom Block
 * @Block(
 * id = "block_form_crypto",
 * admin_label = @Translation("Form crypto"),
 * )
 */
class form_crypto extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
<p class="text-align-center">
    <script id="tgb-widget-script">
          !function t(e,i,n,g,r,s,d,a,y,w,c,o){var p="tgbWidgetOptions";e[p]?(e[p]=e[p].length?e[p]:[e[p]],
          e[p].push({id:r,domain:g,buttonId:d,scriptId:s,uiVersion:a,donationFlow:y,fundraiserId:w}))
          :e[p]={id:r,domain:g,buttonId:d,scriptId:s,uiVersion:a,donationFlow:y,fundraiserId:w},
          (c=i.createElement(n)).src=[g,"widget/script.js"].join(""),c.async=1,
          (o=i.getElementById(s)).parentNode.insertBefore(c,o)
          }(window,document,"script","https://tgbwidget.com/","131614906","tgb-widget-script","tgb-widget-button", "2", "", "");
        </script>
</p>
      '),
    );

  }
}

