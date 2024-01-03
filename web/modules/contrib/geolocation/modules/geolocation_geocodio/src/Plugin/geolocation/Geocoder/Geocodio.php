<?php

namespace Drupal\geolocation_geocodio\Plugin\geolocation\Geocoder;

use Drupal;

use GuzzleHttp\Exception\RequestException;
use Drupal\geolocation\GeocoderBase;
use Drupal\geolocation\GeocoderInterface;
use Geocodio\Geocodio as GeocodioApi;
use Drupal\geolocation\KeyProvider;

/**
 * Provides a Geocodio integration.
 *
 * @Geocoder(
 *   id = "geocodio",
 *   name = @Translation("Geocodio"),
 *   description = @Translation("See https://www.geocod.io/docs/ for details."),
 *   locationCapable = true,
 *   boundaryCapable = false,
 *   frontendCapable = false,
 * )
 */
class Geocodio extends GeocoderBase implements GeocoderInterface {

  /**
   * {@inheritdoc}
   */
  public function geocode(string $address): ?array {

    if (empty($address)) {
      return NULL;
    }

    // Get config.
    $config = Drupal::config('geolocation_geocodio.settings');
    $fields = $config->get('fields');
    $location = [];

    // Set up connection to geocod.io.
    $geocoder = new GeocodioAPI();
    $key = KeyProvider::getKeyValue($config->get('api_key'));
    $geocoder->setApiKey($key);

    // Attempt to geolocate address.
    try {
      // If fields are defined in settings pull associated
      // metadata.
      if (!empty($fields)) {
        $fields = explode(',', $fields);
        $result = $geocoder->geocode($address, $fields);
      }
      // Otherwise do a stright query.
      else {
        $result = $geocoder->geocode($address);
      }
    }
    catch (RequestException $e) {
      watchdog_exception('geolocation', $e);
      return NULL;
    }

    $results = $result->results[0] ?? FALSE;

    // If no results, return false.
    if (!$results) {
      return NULL;
    }
    // Otherwise add location, formatted address and fields.
    else {
      $location['location'] = [
        'lat' => $results->location->lat,
        'lng' => $results->location->lng,
      ];
    }
    // Add formatted address if it exists.
    if (!empty($results->formatted_address)) {
      $location['address'] = $results->formatted_address;
    }
    // Add metadata coming from fields if it exists.
    if (!empty($results->fields)) {
      $location['metadata'] = $results->fields;
    }

    return $location;
  }

}
