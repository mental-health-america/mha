<?php

namespace Drupal\geolocation\Plugin\geolocation\Location;


use Drupal\geolocation\LocationInterface;
use Drupal\geolocation\LocationBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fixed coordinates map center.
 *
 * @Location(
 *   id = "ipstack",
 *   name = @Translation("ipstack Service"),
 *   description = @Translation("See https://ipstack.com/ website. Limited to 10000 requests per month. Access key required."),
 * )
 */
class IpStack extends LocationBase implements LocationInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Request $request
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): LocationInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return [
      'access_key' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(string $location_option_id = NULL, array $settings = [], $context = NULL): array {
    $settings = $this->getSettings($settings);

    $form['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#default_value' => $settings['access_key'],
      '#size' => 60,
      '#maxlength' => 128,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoordinates(string $location_option_id, array $location_option_settings, $context = NULL): array {
    $settings = $this->getSettings($location_option_settings);
    // Access Key is required.
    if (empty($settings['access_key'])) {
      return [];
    }

    // Get client IP.
    $ip = $this->request->getClientIp();
    if (empty($ip)) {
      return [];
    }

    // Get data from api.ipstack.com.
    $json = file_get_contents("https://api.ipstack.com/" . $ip . "?access_key=" . $settings['access_key']);
    if (empty($json)) {
      return [];
    }

    $result = json_decode($json, TRUE);
    if (
      empty($result)
      || empty($result['latitude'])
      || empty($result['longitude'])
    ) {
      return [];
    }

    return [
      'lat' => (float) $result['latitude'],
      'lng' => (float) $result['longitude'],
    ];
  }

}
