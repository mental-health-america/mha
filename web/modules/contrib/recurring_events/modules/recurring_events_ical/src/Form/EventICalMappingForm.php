<?php

namespace Drupal\recurring_events_ical\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\token\TokenEntityMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the event iCal property mapping add/edit form.
 */
class EventICalMappingForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\recurring_events_ical\EventICalMappingInterface
   */
  protected $entity;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The token entity mapper service.
   *
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $tokenEntityMapper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo */
    $entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $container->get('entity_type.manager');
    /** @var \Drupal\token\TokenEntityMapperInterface $tokenEntityMapper */
    $tokenEntityMapper = $container->get('token.entity_mapper');
    return new static(
      $entityTypeBundleInfo,
      $entityTypeManager,
      $tokenEntityMapper
    );
  }

  /**
   * Constructs an EventICalMappingForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\token\TokenEntityMapperInterface $tokenEntityMapper
   *   The token entity mapper service.
   */
  public function __construct(EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityTypeManagerInterface $entityTypeManager, TokenEntityMapperInterface $tokenEntityMapper) {
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->tokenEntityMapper = $tokenEntityMapper;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $eventType = $this->entity->isNew() ? $form_state->get('event_type') : $this->entity->id();

    // If this is a new mapping, show a selector for unmapped event types.
    if ($this->entity->isNew()) {
      $form['#ajax_wrapper_id'] = 'event-ical-mapping-form-ajax-wrapper';
      $ajax = [
        'wrapper' => $form['#ajax_wrapper_id'],
        'callback' => '::rebuildForm',
      ];
      $form['#prefix'] = '<div id="' . $form['#ajax_wrapper_id'] . '">';
      $form['#suffix'] = '</div>';

      $form['id'] = [
        '#type' => 'select',
        '#title' => $this->t('Event instance type'),
        '#description' => $this->t('Select the type of event for which to map iCalendar properties.'),
        '#options' => $this->getUnmappedTypes(),
        '#default_value' => $eventType,
        '#required' => TRUE,
        '#ajax' => $ajax + [
          'trigger_as' => [
            'name' => 'select_id_submit',
          ],
        ],
      ];
      $form['select_id_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#name' => 'select_id_submit',
        '#ajax' => $ajax,
        '#attributes' => [
          'class' => ['js-hide'],
        ],
      ];
    }

    // Hide the rest of the form until a type is selected.
    if (!isset($eventType)) {
      return $form;
    }

    // Show the token browser.
    $tokenTypes = [
      'eventinstance' => $this->tokenEntityMapper->getTokenTypeForEntityType('eventinstance'),
    ];
    $form['token_browser'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $tokenTypes,
      '#global_types' => TRUE,
      '#show_nested' => TRUE,
    ];

    // iCalendar properties.
    $form['properties'] = ['#tree' => TRUE];
    $propertyDefaults = [
      '#type' => 'textfield',
      '#size' => 65,
      '#maxlength' => 1280,
      '#element_validate' => ['token_element_validate'],
      '#after_build' => ['token_element_validate'],
      '#token_types' => $tokenTypes,
    ];
    $form['properties']['summary'] = [
      '#title' => $this->t('Summary'),
      '#default_value' => $this->entity->getProperty('summary') ?? '[eventinstance:title]',
      '#description' => $this->t('Short summary or subject for the event.'),
      '#required' => TRUE,
    ] + $propertyDefaults;
    $form['properties']['contact'] = [
      '#title' => $this->t('Contact'),
      '#default_value' => $this->entity->getProperty('contact'),
      '#description' => $this->t('Contact information for the event.'),
    ] + $propertyDefaults;
    $form['properties']['description'] = [
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->getProperty('description') ?? '[eventinstance:description]',
      '#description' => $this->t('A more complete description of the event than that provided by the summary.'),
    ] + $propertyDefaults;
    $form['properties']['geo'] = [
      '#title' => $this->t('Geographic Position'),
      '#default_value' => $this->entity->getProperty('geo'),
      '#description' => $this->t('The global position for the event. The value must be two semicolon-separated float values.'),
    ] + $propertyDefaults;
    $form['properties']['location'] = [
      '#title' => $this->t('Location'),
      '#default_value' => $this->entity->getProperty('location'),
      '#description' => $this->t('The intended venue for the event.'),
    ] + $propertyDefaults;
    $form['properties']['priority'] = [
      '#title' => $this->t('Priority'),
      '#default_value' => $this->entity->getProperty('priority'),
      '#description' => $this->t('The relative priority of the event. The value must be an integer in the range 0 to 9. A value of 0 specifies an undefined priority. A value of 1 is the highest priority. A value of 9 is the lowest priority.'),
    ] + $propertyDefaults;
    $form['properties']['url'] = [
      '#title' => $this->t('URL'),
      '#default_value' => $this->entity->getProperty('url') ?? '[eventinstance:url]',
      '#description' => $this->t('A URL associated with the event.'),
    ] + $propertyDefaults;

    return $form;
  }

  /**
   * Ajax form submit handler that returns the rebuilt form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function rebuildForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    if ($this->entity->isNew() && empty($form['id']['#options'])) {
      $form['id'] = [
        '#markup' => $this->t('All event instance types are already mapped.'),
      ];
      unset($form['actions']['submit']);
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => new Url('entity.event_ical_mapping.collection'),
        '#attributes' => [
          'class' => [
            'button',
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);
    if ($entity->isNew()) {
      $type = $form_state->getValue('id');
      $types = $this->entityTypeBundleInfo->getBundleInfo('eventinstance');
      $entity->set('label', $types[$type]['label']);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] === 'select_id_submit') {
      $form_state->set('event_type', $form_state->getValue('id'));
      $form_state->setRebuild();
    }
    else {
      parent::submitForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    $this->messenger()->addMessage($this->t('%label iCalendar property mapping saved.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }

  /**
   * Returns an array of unmapped event instance types.
   *
   * @return array
   *   A list of unmapped event instance types as $id => $label.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUnmappedTypes(): array {
    $unmappedTypes = [];
    $types = $this->entityTypeBundleInfo->getBundleInfo('eventinstance');
    if ($types) {
      $mappedTypes = $this->entityTypeManager->getStorage('event_ical_mapping')->loadMultiple(array_keys($types));
      foreach ($types as $type => $info) {
        if (!isset($mappedTypes[$type])) {
          $unmappedTypes[$type] = $info['label'];
        }
      }
    }
    return $unmappedTypes;
  }

}
