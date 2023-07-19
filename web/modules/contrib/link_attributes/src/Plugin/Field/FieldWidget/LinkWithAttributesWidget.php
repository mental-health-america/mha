<?php

namespace Drupal\link_attributes\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\link_attributes\LinkAttributesManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'link' widget.
 *
 * @FieldWidget(
 *   id = "link_attributes",
 *   label = @Translation("Link (with attributes)"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkWithAttributesWidget extends LinkWidget implements ContainerFactoryPluginInterface {

  public const WIDGET_OPEN_EXPAND_IF_VALUES_SET = 'expandIfValuesSet';
  public const WIDGET_OPEN_COLLAPSED = 'collapsed';
  public const WIDGET_OPEN_EXPANDED = 'expanded';

  /**
   * The link attributes manager.
   *
   * @var \Drupal\link_attributes\LinkAttributesManager
   */
  protected $linkAttributesManager;

  /**
   * Constructs a LinkWithAttributesWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\link_attributes\LinkAttributesManager $link_attributes_manager
   *   The link attributes manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, LinkAttributesManager $link_attributes_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->linkAttributesManager = $link_attributes_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.link_attributes')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'placeholder_url' => '',
      'placeholder_title' => '',
      'enabled_attributes' => [
        'id' => FALSE,
        'name' => FALSE,
        'target' => TRUE,
        'rel' => TRUE,
        'class' => TRUE,
        'accesskey' => FALSE,
      ],
      'widget_default_open' => self::WIDGET_OPEN_EXPAND_IF_VALUES_SET,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = $items[$delta];

    $options = $item->get('options')->getValue();
    $attributes = $options['attributes'] ?? [];

    // Condition to check if there are any enabled attributes, if not, an
    // empty element is returned:
    if (empty(array_filter($this->getSetting('enabled_attributes')))) {
      return $element;
    }

    $widgetDefaultOpenSetting = $this->getSetting('widget_default_open');
    $open = NULL;

    match ($widgetDefaultOpenSetting) {
      self::WIDGET_OPEN_EXPAND_IF_VALUES_SET => $open = count($attributes),
      self::WIDGET_OPEN_EXPANDED => $open = TRUE,
      self::WIDGET_OPEN_COLLAPSED => $open = FALSE,
      default => $open = count($attributes),
    };

    $element['options']['attributes'] = [
      '#type' => 'details',
      '#title' => $this->t('Attributes'),
      '#tree' => TRUE,
      '#open' => $open,
    ];
    $plugin_definitions = $this->linkAttributesManager->getDefinitions();
    foreach (array_keys(array_filter($this->getSetting('enabled_attributes'))) as $attribute) {
      if (isset($plugin_definitions[$attribute])) {
        foreach ($plugin_definitions[$attribute] as $property => $value) {
          if ($property === 'id') {
            // Don't set ID.
            continue;
          }
          $element['options']['attributes'][$attribute]['#' . $property] = $value;
        }

        // Set the default value, in case of a class that is stored as array,
        // convert it back to a string.
        $default_value = $attributes[$attribute] ?? NULL;
        if ($attribute === 'class' && is_array($default_value)) {
          $default_value = implode(' ', $default_value);
        }
        if (isset($default_value)) {
          $element['options']['attributes'][$attribute]['#default_value'] = $default_value;
        }
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $options = array_map(function ($plugin_definition) {
      return $plugin_definition['title'];
    }, $this->linkAttributesManager->getDefinitions());
    $selected = array_keys(array_filter($this->getSetting('enabled_attributes')));
    $element['enabled_attributes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled attributes'),
      '#options' => $options,
      '#default_value' => array_combine($selected, $selected),
      '#description' => $this->t('Select the attributes to allow the user to edit.'),
    ];
    $element['widget_default_open'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget default open behavior'),
      '#options' => [
        self::WIDGET_OPEN_EXPAND_IF_VALUES_SET => $this->t('Expand if values set (Default)'),
        self::WIDGET_OPEN_EXPANDED => $this->t('Expand'),
        self::WIDGET_OPEN_COLLAPSED => $this->t('Collapse'),
      ],
      '#default_value' => $this->getSetting('default_open') ?? self::WIDGET_OPEN_EXPAND_IF_VALUES_SET,
      '#description' => $this->t('Set the widget default open behavior.'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Convert a class string to an array so that it can be merged reliable.
    foreach ($values as $delta => $value) {
      if (isset($value['options']['attributes']['class']) && is_string($value['options']['attributes']['class'])) {
        $values[$delta]['options']['attributes']['class'] = explode(' ', $value['options']['attributes']['class']);
      }
    }

    return array_map(function (array $value) {
      if (isset($value['options']['attributes'])) {
        $value['options']['attributes'] = array_filter($value['options']['attributes'], function ($attribute) {
          return $attribute !== "";
        });
      }
      return $value;
    }, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $enabled_attributes = array_filter($this->getSetting('enabled_attributes'));
    if ($enabled_attributes) {
      $summary[] = $this->t('With attributes: @attributes', ['@attributes' => implode(', ', array_keys($enabled_attributes))]);
    }
    $widgetDefaultOpenSetting = $this->getSetting('widget_default_open');

    match ($widgetDefaultOpenSetting) {
      self::WIDGET_OPEN_EXPAND_IF_VALUES_SET => $summary[] = $this->t('Widget open if values set'),
      self::WIDGET_OPEN_EXPANDED => $summary[] = $this->t('Widget open by default.'),
      self::WIDGET_OPEN_COLLAPSED => $summary[] = $this->t('Widget closed by default.'),
    };
    return $summary;
  }

}
