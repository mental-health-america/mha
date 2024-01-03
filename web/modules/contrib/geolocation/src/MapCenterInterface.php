<?php

namespace Drupal\geolocation;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for geolocation MapCenter plugins.
 */
interface MapCenterInterface extends PluginInspectionInterface {

  /**
   * Provide a populated settings array.
   *
   * @return array
   *   The settings array with the default map settings.
   */
  public static function getDefaultSettings(): array;

  /**
   * Provide MapCenter option specific settings.
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
   * @param string $option_id
   *   MapCenter option ID.
   * @param array $settings
   *   The current option settings.
   * @param array $context
   *   Current context.
   *
   * @return array
   *   A form array to be integrated in whatever.
   */
  public function getSettingsForm(string $option_id, array $settings, array $context = []): array;

  /**
   * Validate.
   *
   * @param array $values
   *   Values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function validateSettingsForm(array $values, FormStateInterface $form_state): void;

  /**
   * For one MapCenter (i.e. boundary filter), return all options (all filters).
   *
   * @param array $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Available center options indexed by ID.
   */
  public function getAvailableMapCenterOptions(array $context = []): array;

  /**
   * Alter map..
   *
   * @param array $render_array
   *   Render array.
   * @param string $center_option_id
   *   MapCenter option ID.
   * @param int $weight
   *   Option weight.
   * @param array $center_option_settings
   *   The current feature settings.
   * @param array $context
   *   Context like field formatter, field widget or view.
   *
   * @return array
   *   Map object.
   */
  public function alterMap(array $render_array, string $center_option_id, int $weight, array $center_option_settings = [], array $context = []): array;

}
