<?php

namespace Drupal\geolocation_leaflet\Plugin\geolocation\Geocoder;

use Drupal;

use Drupal\geolocation\GeocoderBase;
use Drupal\geolocation\GeocoderInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Url;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Provides the Photon.
 *
 * @Geocoder(
 *   id = "photon",
 *   name = @Translation("Photon"),
 *   description = @Translation("See https://photon.komoot.io for details."),
 *   locationCapable = true,
 *   boundaryCapable = true,
 *   frontendCapable = true,
 *   reverseCapable = true,
 * )
 */
class Photon extends GeocoderBase implements GeocoderInterface {

  /**
   * Base URL.
   *
   * @var string
   *   Photon URL.
   */
  public string $requestBaseUrl = 'https://photon.komoot.io';

  /**
   * {@inheritdoc}
   */
  protected function getDefaultSettings(): array {
    $default_settings = parent::getDefaultSettings();

    $default_settings['location_priority'] = [
      'lat' => '',
      'lng' => '',
    ];

    $default_settings['remove_duplicates'] = FALSE;

    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsForm(): array {

    $settings = $this->getSettings();

    $form = parent::getOptionsForm();

    $form['location_priority'] = [
      '#type' => 'geolocation_input',
      '#title' => $this->t('Location Priority'),
      '#default_value' => [
        'lat' => $settings['location_priority']['lat'],
        'lng' => $settings['location_priority']['lng'],
      ],
    ];

    $form['remove_duplicates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove duplicates from the Photon API'),
      '#default_value' => $settings['remove_duplicates'],
      '#description' => $this->t('The Photon API can generate duplicates for some locations (i.e. cities that are states for example), this option will remove them.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function geocode(string $address): ?array {
    if (empty($address)) {
      return NULL;
    }

    $options = [
      'q' => $address,
      'limit' => 1,
    ];

    $lang = Drupal::languageManager()->getCurrentLanguage()->getId();
    if (in_array($lang, ['de', 'en', 'fr'])) {
      $options['lang'] = $lang;
    }

    $url = Url::fromUri($this->requestBaseUrl . '/api/', [
      'query' => $options,
    ]);

    try {
      $result = Json::decode(Drupal::httpClient()->get($url->toString())->getBody());
    }
    catch (RequestException $e) {
      watchdog_exception('geolocation', $e);
      return NULL;
    }

    $location = [];

    if (empty($result['features'][0])) {
      return NULL;
    }
    else {
      $location['location'] = [
        'lat' => $result['features'][0]['geometry']['coordinates'][1],
        'lng' => $result['features'][0]['geometry']['coordinates'][0],
      ];
    }

    if (!empty($result[0]['boundingbox'])) {
      $location['boundary'] = [
        'lat_north_east' => $result[0]['boundingbox'][1],
        'lng_north_east' => $result[0]['boundingbox'][3],
        'lat_south_west' => $result[0]['boundingbox'][0],
        'lng_south_west' => $result[0]['boundingbox'][2],
      ];
    }

    if (!empty($result[0]['display_name'])) {
      $location['address'] = $result[0]['display_name'];
    }

    return $location;
  }

  /**
   * {@inheritdoc}
   */
  public function reverseGeocode(float $latitude, float $longitude): ?array {
    $url = Url::fromUri($this->requestBaseUrl . '/reverse', [
      'query' => [
        'lat' => $latitude,
        'lon' => $longitude,
        'limit' => 20,
      ],
    ]);

    try {
      $result = Json::decode(Drupal::httpClient()->get($url->toString())->getBody());
    }
    catch (RequestException $e) {
      watchdog_exception('geolocation', $e);
      return NULL;
    }

    if (empty($result['features'][0]['properties'])) {
      return NULL;
    }

    $countries = Drupal::service('address.country_repository')->getList();
    $address_atomics = [];
    foreach ($result['features'] as $entry) {
      if (empty($entry['properties']['osm_type'])) {
        continue;
      }

      switch ($entry['properties']['osm_type']) {
        case 'N':
          $address_atomics = [
            'houseNumber' => !empty($entry['properties']['housenumber']) ? $entry['properties']['housenumber'] : '',
            'road' => $entry['properties']['street'],
            'city' => $entry['properties']['city'],
            'postcode' => $entry['properties']['postcode'],
            'state' => $entry['properties']['state'],
            'country' => $entry['properties']['country'],
            'countryCode' => array_search($entry['properties']['country'], $countries),
          ];
          break 2;
      }
    }

    if (empty($address_atomics)) {
      return NULL;
    }

    return [
      'atomics' => $address_atomics,
      'elements' => $this->addressElements($address_atomics),
      'formatted_address' => empty($result['display_name']) ? '' : $result['display_name'],
    ];
  }

}
