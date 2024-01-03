<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for geolocation MapFeature plugins.
 */
interface MapFeatureInterface extends PluginInspectionInterface {

  /**
   * Provide a populated settings array.
   *
   * @return array
   *   The settings array with the default map settings.
   */
  public static function getDefaultSettings(): array;

  /**
   * Provide map feature specific settings ready to handover to JS.
   *
   * @param array $settings
   *   Current general map settings. Might contain unrelated settings as well.
   * @param \Drupal\geolocation\MapProviderInterface|null $mapProvider
   *   Map provider.
   *
   * @return array
   *   An array only containing keys defined in this plugin.
   */
  public function getSettings(array $settings, MapProviderInterface $mapProvider = NULL): array;

  /**
   * Provide a summary array to use in field formatters.
   *
   * @param array $settings
   *   The current map settings.
   * @param \Drupal\geolocation\MapProviderInterface|null $mapProvider
   *   Map provider.
   *
   * @return array
   *   An array to use as field formatter summary.
   */
  public function getSettingsSummary(array $settings, MapProviderInterface $mapProvider = NULL): array;

  /**
   * Provide a generic map settings form array.
   *
   * @param array $settings
   *   The current map settings.
   * @param array $parents
   *   Form specific optional prefix.
   * @param \Drupal\geolocation\MapProviderInterface|null $mapProvider
   *   Map provider.
   *
   * @return array
   *   A form array to be integrated in whatever.
   */
  public function getSettingsForm(array $settings, array $parents = [], MapProviderInterface $mapProvider = NULL): array;

  /**
   * Validate Feature Form.
   *
   * @param array $values
   *   Feature values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State.
   * @param array $parents
   *   Element parents.
   */
  public function validateSettingsForm(array $values, FormStateInterface $form_state, array $parents = []);

  /**
   * Alter render array.
   *
   * @param array $render_array
   *   Render array.
   * @param array $feature_settings
   *   The current feature settings.
   * @param array $context
   *   Context like field formatter, field widget or view.
   * @param \Drupal\geolocation\MapProviderInterface|null $mapProvider
   *   Map provider.
   *
   * @return array
   *   Render array.
   */
  public function alterMap(array $render_array, array $feature_settings = [], array $context = [], MapProviderInterface $mapProvider = NULL): array;

}
