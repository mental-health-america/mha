<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for geolocation LocationInput plugins.
 */
interface LocationInputInterface extends PluginInspectionInterface {

  /**
   * Provide a populated settings array.
   *
   * @return array
   *   The settings array with the default plugin settings.
   */
  public static function getDefaultSettings(): array;

  /**
   * Provide LocationInput option specific settings.
   *
   * @param array $settings
   *   Current settings.
   *
   * @return array
   *   An array only containing keys defined in this plugin.
   */
  public function getSettings(array $settings): array;

  /**
   * Settings form by ID and context.
   *
  * @param array $settings
   *   The current option settings.
   * @param array $context
   *   Current context.
   *
   * @return array
   *   A form array to be integrated in whatever.
   */
  public function getSettingsForm(array $settings, array $context = []): array;

  /**
   * For one LocationInput (i.e. boundary filter), return all options.
   *
   * @param array $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Available center options indexed by ID.
   */
  public function getAvailableLocationInputOptions(array $context = []): array;

  /**
   * Get center value.
   *
   * @param array $form_value
   *   Form value.
   * @param string $location_input_option_id
   *   LocationInput option ID.
   * @param array $location_input_option_settings
   *   The current feature settings.
   * @param array $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Render array.
   */
  public function getCoordinates(array $form_value, array $settings, array $context = []): array;

  public function alterForm(array $form, array $settings, array $context = [], array $default_value = NULL): array;

}
