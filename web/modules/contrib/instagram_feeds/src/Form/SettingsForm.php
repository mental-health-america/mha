<?php

namespace Drupal\instagram_feeds\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;

/**
 * Defines an Instagram Feeds configuration form.
 */
class SettingsForm extends ConfigFormBase {

  const SETTINGS = 'instagram_feeds.settings';

  /**
   * Token descriptions.
   *
   * @var array
   */
  private $tokenDescription;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'instagram_feeds_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);
    $base_field = ['#type' => 'textfield', '#required' => TRUE];

    $form['client_id'] = [
      '#title' => $this->t('Instagram App (Client) ID'),
      '#default_value' => $config->get('client_id'),
      ] + $base_field;

    $form['client_secret'] = [
      '#title' => $this->t('Instagram App (Client) Secret'),
      '#default_value' => $config->get('client_secret'),
      '#description' => $this->t('You may want to setup this via settings.php, that\'s why it is not required here.'),
      '#required' => FALSE,
      ] + $base_field;

    $form['refresh_frequency'] = [
      '#type' => 'select',
      '#options' => $this->getRefreshFrequencyOptions(),
      '#title' => $this->t('Long-Lived Token Refresh Frequency'),
      '#description' => $this->t('The long-lived token is valid only 60 days. After it was expired it is no longer possible to refresh it. Also it cannot be refreshed more often than once per 24 hours.'),
      '#field_prefix' => $this->t('Refresh every'),
      '#field_suffix' => $this->t('by Cron'),
      '#default_value' => $config->get('refresh_frequency'),
    ];

    $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $form['avatar_uri_scheme'] = [
      '#type' => 'radios',
      '#title' => t('Avatar upload destination'),
      '#options' => $scheme_options,
      '#default_value' => $config->get('avatar_uri_scheme'),
      '#description' => t('Select where the final files should be stored. Private file storage has significantly more overhead than public files, but allows restricted access to files within this field.'),
      ] + $base_field;

    $form['avatar_directory'] = [
      '#title' => $this->t('Avatar directory'),
      '#description' => $this->t('Where to store avatars, for example: "instagram_avatars".'),
      '#default_value' => $config->get('avatar_directory'),
      '#element_validate' => ['\Drupal\file\Plugin\Field\FieldType\FileItem::validateDirectory'],
      ] + $base_field + $this->getTokenDescription();

    $form['mapping'] = $this->buildMappingForm($form, $form_state, 'mapping');

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form builder for mapping between media entity and Instagram post.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $field_name
   *   Parent field name to build the form tree.
   *
   * @return array
   *   Fields mapping form element array.
   */
  protected function buildMappingForm(array $form, FormStateInterface $form_state, $field_name): array {
    $config = $this->config(self::SETTINGS);
    $element = [
      '#title' => $this->t('Fields Mapping'),
      '#type' => 'details',
      '#description' => $this->t('Fields mapping between supported Media types and data from Instagram API.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $media_types = $this->getInstagramMediaTypes();
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager */
    $entity_field_manager = \Drupal::service('entity_field.manager');
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    foreach ($media_types as $media_type_id => $media_type) {
      $element[$media_type_id] = [
        '#type' => 'details',
        '#title' => $media_type->label(),
        '#open' => count($media_types) === 1,
        '#tree' => TRUE,
      ];
      $permalink_field_name = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();
      foreach ($entity_field_manager->getFieldDefinitions('media', $media_type_id) as $field_definition) {
        if ($field_definition->getName() == $permalink_field_name) {
          $element[$media_type_id][$field_definition->getName()] = [
            '#type' => 'item',
            '#field_prefix' => $field_definition->getLabel() . ': ',
            '#parents' => [],
            '#field_suffix' => $this->getInstagramApiFields()['permalink'],
          ];
        }
        elseif ($this->isMappableFeild($field_definition)) {
          $element[$media_type_id][$field_definition->getName()] = [
            '#type' => 'select',
            '#options' => $this->getInstagramApiFields($field_definition->getType()),
            '#empty_option' => $this->t('- Skip -'),
            '#field_prefix' => $field_definition->getLabel(),
            '#default_value' => $config->get('mapping.' . $media_type_id . '.' . $field_definition->getName()),
          ];
        }
        elseif ($field_definition->getName() == 'name') {
          $element[$media_type_id]['name'] = [
            '#type' => 'textfield',
            '#field_prefix' => $this->t('Media entity name'),
            '#required' => TRUE,
            '#default_value' => $config->get('mapping.' . $media_type_id . '.name')
          ] + $this->getTokenDescription();
        }
      }
    }
    return $element;
  }

  /**
   * Returns form element array with token suggestions.
   *
   * @param array $types
   *   The list of allowed token types. If empty, all global types will
   *   be allowed.
   *
   * @return array
   *   Array to join with element with tokens support.
   */
  public function getTokenDescription(array $types = [
    'site',
    'random',
    'current-date',
    'current-user',
    'instagram-account',
    'instagram-post',
  ]) {
    sort($types);
    $key = md5(serialize($types));
    if (!isset($this->tokenDescription[$key])) {
      $this->setTokenDescription($types);
    }
    return $this->tokenDescription[$key];
  }

  /**
   * Creates element array keys with token suggestions.
   *
   * @param array $types
   *   The list of allowed token types. If empty, all global types will
   *   be allowed.
   *
   * @return $this
   */
  protected function setTokenDescription(array $types = []) {
    sort($types);
    $key = md5(serialize($types));
    $this->tokenDescription[$key] = [];
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $token_tree = [
        '#theme' => 'token_tree_link',
        '#token_types' => $types,
        '#global_types' => count($types) ? FALSE : TRUE,
        '#click_insert' => TRUE,
      ];
      $this->tokenDescription[$key] = [
        '#field_suffix' => $this->t('This field supports tokens. @browse_tokens_link', [
          '@browse_tokens_link' => \Drupal::service('renderer')->render($token_tree),
        ]),
        '#element_validate' => ['token_element_validate'],
        '#token_types' => $types,
      ];
    }

    return $this;
  }

  /**
   * Checks if media entity field can be mapped with Instagram data.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Media entity field definition.
   *
   * @return bool
   *   TRUE if field can be mapped with Instagram data, FALSE otherwise.
   */
  protected function isMappableFeild($field_definition): bool {
    if ($field_definition->isInternal() || $field_definition->isReadOnly() || !$field_definition->isDisplayConfigurable('view')) {
      return FALSE;
    }
    $prohibited_types = ['boolean', 'changed', 'language'];
    if (in_array($field_definition->getType(), $prohibited_types)) {
      return FALSE;
    }
    if ('entity_reference' == $field_definition->getType() && 'taxonomy_term' != $field_definition->getSetting('target_type')) {
      return FALSE;
    }
    if (in_array($field_definition->getName(), ['name', 'created'])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Gets all available for import Instagram post fields.
   *
   * @param string $field_type
   *   Optional field type to return only supported Instagram fields for.
   *
   * @return array
   *   The select field options list.
   */
  protected function getInstagramApiFields($field_type = ''): array {
    $options = [
      'id' => $this->t('Post ID'),
      'caption' => $this->t('Caption'),
      'username' => $this->t('Owner username'),
      'media_type' => $this->t('Media type (image, video or gallery)'),
      'media_url' => $this->t('Media URL'),
      'permalink' => $this->t('Permalink'),
      'thumbnail_url' => $this->t('Thumbnail URL'),
      'timestamp' => $this->t('UNIX timestamp'),
      'date' => $this->t('Date'),
      'full_name' => $this->t('Owner full name'),
      'avatar' => $this->t('Avatar'),
      'tags' => $this->t('Hash tags'),
    ];
    $strings = ['id', 'username', 'media_type', 'media_url', 'permalink', 'thumbnail_url', 'full_name', 'tags'];
    switch ($field_type) {
      case 'image':
        return array_intersect_key($options, array_fill_keys(['avatar'], '1'));
      case 'string':
        return array_intersect_key($options, array_fill_keys($strings, '1'));
      case 'string_long':
        return array_intersect_key($options, array_fill_keys(['caption'] + $strings, '1'));
      case 'timestamp':
        return array_intersect_key($options, array_fill_keys(['timestamp'], '1'));
      case 'datetime':
        return array_intersect_key($options, array_fill_keys(['date'], '1'));
      case 'entity_reference':
        return array_intersect_key($options, array_fill_keys(['tags'], '1'));
      case 'ingeger':
        return array_intersect_key($options, array_fill_keys(['id'], '1'));
      default:
        return $options;
    }
  }

  /**
   * Gets Media types with Instagram source.
   *
   * @return \Drupal\media\MediaTypeInterface[]
   *   Media Types options for Select form element.
   */
  protected function getInstagramMediaTypes(): array {
    $options = [];
    $media_types = \Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple();
    foreach ($media_types as $media_type) {
      /** @var \Drupal\media\MediaTypeInterface $media_type */
      if ($media_type->getSource()->getPluginId() === 'instagram') {
        $options[$media_type->id()] = $media_type;
      }
    }
    return $options;
  }

  /**
   * Get options for the Long-Lived Token Refresh Frequency field.
   *
   * @return array
   *   The list of time intervals where keys are time in seconds.
   */
  protected function getRefreshFrequencyOptions() {
    $days = [2, 5, 7, 10, 14, 30, 45, 59];
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $period = array_map(function($val) {
      return $val * 86400;
    }, $days);
    return array_map([$date_formatter, 'formatInterval'], array_combine($period, $period));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->config(self::SETTINGS)->setData(array_filter($form_state->getValues()))->save();
    parent::submitForm($form, $form_state);
  }

}
