<?php

/**
 * @file
 * Contains \Drupal\fullcalendar_legend\Plugin\Block\Bundle.
 */

namespace Drupal\fullcalendar_legend\Plugin\Block;

use Drupal\Core\Link;
use Drupal\field_ui\FieldUI;

/**
 * TODO
 *
 * @Block(
 *   id = "fullcalendar_legend_bundle",
 *   admin_label = @Translation("FullCalendar Legend"),
 *   category = @Translation("Fullcalendar")
 * )
 */
class Bundle extends FullcalendarLegendBase {

  /**
   * {@inheritdoc}
   */
  protected function buildLegend(array $fields) {
    $types = [];

        $bundle_info = \Drupal::service('entity_type.bundle.info')->getAllBundleInfo();

    foreach ($fields as $field_name => $field) {
      $entity_type = $field->getTargetEntityTypeId();
      foreach ($field->getBundles() as $bundle) {
          if (!isset($types[$bundle])) {
            $types[$bundle]['entity_type'] = $entity_type;
            $types[$bundle]['field_name'] = $field_name;
            $types[$bundle]['bundle'] = $bundle;
            $types[$bundle]['label'] = $bundle_info[$entity_type][$bundle]['label'];
        }
      }
    }

    return $types;
  }

}
