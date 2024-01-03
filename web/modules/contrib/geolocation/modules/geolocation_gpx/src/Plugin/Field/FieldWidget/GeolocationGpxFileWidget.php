<?php

namespace Drupal\geolocation_gpx\Plugin\Field\FieldWidget;

use Drupal;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geolocation_gpx\Entity\GeolocationGpx;
use Drupal\geolocation_gpx\Entity\GeolocationGpxLink;
use Drupal\geolocation_gpx\Entity\GeolocationGpxRoute;
use Drupal\geolocation_gpx\Entity\GeolocationGpxTrack;
use Drupal\geolocation_gpx\Entity\GeolocationGpxTrackSegment;
use Drupal\geolocation_gpx\Entity\GeolocationGpxWaypoint;
use Exception;
use phpGPX\Models\GpxFile;
use phpGPX\Models\Link;
use phpGPX\Models\Point;
use phpGPX\Models\Route;
use phpGPX\Models\Segment;
use phpGPX\Models\Track;
use phpGPX\phpGPX;

/**
 * Provides a custom field widget.
 *
 * @FieldWidget(
 *   id = "geolocation_gpx_file",
 *   label = @Translation("Geolocation GPX File"),
 *   field_types = {
 *     "geolocation_gpx"
 *   }
 * )
 */
class GeolocationGpxFileWidget extends WidgetBase {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings) {
    $this->entityTypeManager = Drupal::entityTypeManager();

    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, Array $element, Array &$form, FormStateInterface $form_state): array {
    $value = $items[$delta]->gpx_id ?? NULL;

    if ($value) {
      /** @var GeolocationGpx $gpx */
      $gpx = $this->entityTypeManager->getStorage('geolocation_gpx')->load($value) ?? NULL;
      if ($gpx) {
        $element['summary'] = $gpx->renderedSummaryTable();
      }
    }

    $element['gpx_id'] = [
      '#type' => 'file',
      '#title' => $this->t('GPX File'),
      '#upload_validators' => [
        'file_validate_extensions' => ['gpx xml'],
      ],
      '#description' => $this->t('Allowed file types: <i>gpx, xml</i>. The uploaded file will be parsed and the structure imported, <b>replacing</b> any existing. The file will not be permanently stored.'),
    ];

    return $element;
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    parent::massageFormValues($values, $form, $form_state);

    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $files */
    $files = Drupal::request()?->files?->get('files', []);

    if (empty($files[$this->fieldDefinition->getName()])) {
      return [];
    }

    $file_path = $files[$this->fieldDefinition->getName()]->getRealPath();

    try {
      $gpxFile = (new phpGPX())?->load($file_path);
    }
    catch (Exception $e) {
      Drupal::messenger()->addWarning('Could not instantiate GPX file: ' . $e->getMessage());
    }

    if (empty($gpxFile)) {
      return [];
    }

    $gpx = $this->gpxByData($gpxFile);

    $values[0]['gpx_id'] = $gpx->id();

    return $values;
  }

  protected function gpxByData(GpxFile $data): GeolocationGpx {
    $currentUser = Drupal::currentUser();

    /** @var \Drupal\geolocation_gpx\Entity\GeolocationGpx $gpx */
    $gpx = $this->entityTypeManager->getStorage('geolocation_gpx')->create([
      'version' => '1.1',
      'creator' => $data->creator ?? $currentUser->getAccountName(),
      'name' => $data->metadata?->name ?? '',
      'description' => $data->metadata?->description ?? '',
      'author' => $data->metadata?->author ?? '',
      'copyright' => $data->metadata->copyright ?? '',
      'time' => $data->metadata?->time?->format('Y-m-d H:i:s') ?? '',
      'keywords' => $data->metadata?->keywords,
    ]);
    foreach ($data->metadata?->links ?? [] as $linkData) {
      $gpx->get('link')->appendItem($this->linkByData($linkData));
    }

    foreach ($data->waypoints as $waypointData) {
      $gpx->get('waypoints')->appendItem($this->waypointByData($waypointData));
    }

    foreach ($data->routes as $routeData) {
      $gpx->get('routes')->appendItem($this->routeByData($routeData));
    }

    foreach ($data->tracks as $trackData) {
      $gpx->get('tracks')->appendItem($this->trackByData($trackData));
    }

    $gpx->save();

    return $gpx;
  }

  protected function waypointByData(Point $data): GeolocationGpxWaypoint {
    /** @var GeolocationGpxWaypoint $waypoint */
    $waypoint = $this->entityTypeManager->getStorage('geolocation_gpx_waypoint')->create([
      'latitude' => $data->latitude,
      'longitude' => $data->longitude,
      'elevation' => $data->elevation ?? NULL,
      'time' => $data->time?->format('Y-m-d H:i:s') ?? NULL,
      'magnetic_variation' => $data->time ?? NULL,
      'geoidheight' => $data->geoidHeight ?? NULL,
      'name' => $data->name ?? '',
      'comment' => $data->comment ?? '',
      'description' => $data->description ?? '',
      'source' => $data->source ?? '',
      'symbol' => $data->symbol ?? '',
      'type' => $data->type ?? NULL,
      'satellites' => $data->satellitesNumber ?? NULL,
      'horizontal_dilution' => $data->hdop ?? NULL,
      'vertical_dilution' => $data->vdop ?? NULL,
      'position_dilution' => $data->pdop ?? NULL,
      'age_of_dgps_data' => $data->ageOfGpsData ?? NULL,
    ]);

    foreach ($data->links as $linkData) {
      $link = $this->linkByData($linkData);
      $waypoint->get('link')->appendItem($link);
    }
    $waypoint->save();

    return $waypoint;
  }

  protected function linkByData(Link $data): GeolocationGpxLink {
    /** @var GeolocationGpxLink $link */
    $link = $this->entityTypeManager->getStorage('geolocation_gpx_link')->create([
      'href' => $data->href,
      'type' => $data->type,
      'text' => $data->text,
    ]);
    $link->save();

    return $link;
  }

  protected function routeByData(Route $data): GeolocationGpxRoute {
    /** @var GeolocationGpxRoute $route */
    $route = $this->entityTypeManager->getStorage('geolocation_gpx_route')->create([
      'name' => $data->name ?? '',
      'comment' => $data->comment ?? '',
      'description' => $data->description ?? '',
      'source' => $data->source ?? '',
      'number' => $data->type ?? NULL,
      'type' => $data->type ?? NULL,
    ]);

    foreach ($data->points as $waypointData) {
      $route->get('route_points')->appendItem($this->waypointByData($waypointData));
    }

    foreach ($data->links as $linkData) {
      $route->get('link')->appendItem($this->linkByData($linkData));
    }

    $route->save();
    return $route;
  }

  protected function trackByData(Track $data): GeolocationGpxTrack {
    /** @var GeolocationGpxTrack $track */
    $track = $this->entityTypeManager->getStorage('geolocation_gpx_track')->create([
      'name' => $data->name ?? '',
      'comment' => $data->comment ?? '',
      'description' => $data->description ?? '',
      'source' => $data->source ?? '',
      'number' => $data->type ?? NULL,
      'type' => $data->type ?? NULL,
    ]);

    foreach ($data->segments as $segmentData) {
      $track->get('track_segments')->appendItem($this->trackSegmentByData($segmentData));
    }

    foreach ($data->links as $linkData) {
      $track->get('link')->appendItem($this->linkByData($linkData));
    }

    $track->save();
    return $track;
  }

  protected function trackSegmentByData(Segment $data): GeolocationGpxTrackSegment {
    /** @var GeolocationGpxTrackSegment $track_segment */
    $track_segment = $this->entityTypeManager->getStorage('geolocation_gpx_track_segment')->create();

    foreach ($data->points as $waypointData) {
      $track_segment->get('track_points')->appendItem($this->waypointByData($waypointData));
    }

    $track_segment->save();
    return $track_segment;
  }

}
