<?php

namespace Drupal\geolocation_geometry\Plugin\Validation\Constraint;

use Drupal;
use ReflectionClass;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\geolocation_geometry\GeometryFormat\GeoJSON;
use Drupal\geolocation_geometry\GeometryFormat\WKT;

/**
 * Validates the GeoType constraint.
 */
class GeometryConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {

    if (!is_a($constraint, GeometryConstraint::class)) {
      return;
    }

    if (!isset($value)) {
      return;
    }

    switch (strtolower($constraint->type)) {

      case 'wkt':
        /** @var \Drupal\geolocation_geometry\GeometryType\GeometryTypeInterface $geometry */
        $geometry = WKT::geometryByText($value);
        break;

      case 'geojson':
        /** @var \Drupal\geolocation_geometry\GeometryType\GeometryTypeInterface $geometry */
        $geometry = GeoJSON::geometryByText($value);
        break;

      default:
        $this->context->addViolation('Unknown source type');
        return;
    }

    if (!$geometry) {
      $this->context->addViolation('Could not derive geometry from value');
    }

    if (
      $constraint->geometryType == 'geometry'
      || $constraint->geometryType == 'geometrycollection'
    ) {
      // Geometries catch all types.
      return;
    }

    $geometry_class = (new ReflectionClass($geometry))->getShortName();

    if (strtolower($geometry_class) != $constraint->geometryType) {
      $this->context->addViolation('Type of geometry @class differs from intended type @type', [
        '@class' => $geometry::class,
        '@type' => $constraint->geometryType,
      ]);
    }
  }

}
