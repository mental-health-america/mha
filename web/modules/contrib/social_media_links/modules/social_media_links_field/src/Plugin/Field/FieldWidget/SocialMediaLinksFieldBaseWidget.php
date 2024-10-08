<?php

namespace Drupal\social_media_links_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * It allows to link social media.
 */
abstract class SocialMediaLinksFieldBaseWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Social Media Links Platform Manager container.
   *
   * @var \Drupal\social_media_links\SocialMediaLinksPlatformManager
   */
  protected $socialMediaManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->socialMediaManager = $container->get('plugin.manager.social_media_links.platform');
    return $instance;
  }

  /**
   * Returns the available platforms as options array.
   *
   * @return array
   *   $options.
   */
  protected function getAvailablePlatformOptions() {
    $available_platforms = $this->getAvailablePlatforms();

    $options = [];
    foreach ($available_platforms as $platform_id => $platform) {
      $options[$platform_id] = $platform['name']->render();
    }

    return $options;
  }

  /**
   * Returns the list of platforms that was selected in field settings.
   *
   * @return array
   *   $available_platforms
   */
  protected function getAvailablePlatforms() {
    $platforms = $this->socialMediaManager->getPlatforms();
    $platform_settings = $this->fieldDefinition->getSetting('platforms');

    $available_platforms = [];
    foreach ($platforms as $platform_id => $platform) {
      if (isset($platform_settings[$platform_id]['enabled']) && $platform_settings[$platform_id]['enabled']) {
        $available_platforms[$platform_id] = $platform;
        $available_platforms[$platform_id]['weight'] = $platform_settings[$platform_id]['weight'];
      }
    }

    // If the array if empty no platform was selected which means, that all
    // platforms are available.
    if (empty($available_platforms)) {
      $available_platforms = $platforms;

      foreach ($platforms as $platform_id => $platform) {
        $available_platforms[$platform_id]['weight'] = $platform_settings[$platform_id]['weight'] ?? 0;
      }
    }

    uasort($available_platforms, [
      'Drupal\Component\Utility\SortArray',
      'sortByWeightElement',
    ]);

    return $available_platforms;
  }

}
