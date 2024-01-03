<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for geolocation MapProvider plugins.
 */
interface MapProviderInterface extends PluginInspectionInterface {

  /**
   * Provide a populated settings array.
   *
   * @return array
   *   The settings array with the default map settings.
   */
  public static function getDefaultSettings(): array;

  /**
   * Provide map provider specific settings ready to handover to JS.
   *
   * @param array $settings
   *   Current general map settings. Might contain unrelated settings as well.
   *
   * @return array
   *   An array only containing keys defined in this plugin.
   */
  public function getSettings(array $settings): array;

  /**
   * Provide a summary array to use in field formatters.
   *
   * @param array $settings
   *   The current map settings.
   *
   * @return array
   *   An array to use as field formatter summary.
   */
  public function getSettingsSummary(array $settings): array;

  /**
   * Provide a generic map settings form array.
   *
   * @param array $settings
   *   The current map settings.
   * @param array $parents
   *   Form parents.
   *
   * @return array
   *   A form array to be integrated in whatever.
   */
  public function getSettingsForm(array $settings, array $parents): array;

  /**
   * Alter render array.
   *
   * @param array $render_array
   *   Render array.
   * @param array $map_settings
   *   The current map settings.
   * @param array $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Render attachments.
   */
  public function alterRenderArray(array $render_array, array $map_settings, array $context = []): array;

  /**
   * Return available control positions.
   *
   * @return array|null
   *   Positions.
   */
  public static function getControlPositions(): ?array;

}
