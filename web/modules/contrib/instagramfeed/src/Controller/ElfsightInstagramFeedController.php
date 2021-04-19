<?php

namespace Drupal\elfsight_instagram_feed\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * {@inheritdoc}
 */
class ElfsightInstagramFeedController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    $url = 'https://apps.elfsight.com/embed/instashow/?utm_source=portals&utm_medium=drupal&utm_campaign=instagram-feed&utm_content=sign-up';

    require_once __DIR__ . '/embed.php';

    return [
      'response' => 1,
    ];
  }

}
