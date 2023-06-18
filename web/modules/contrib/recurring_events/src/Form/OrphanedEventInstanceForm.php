<?php

namespace Drupal\recurring_events\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for handling orphaned instances.
 *
 * @ingroup recurring_events
 */
class OrphanedEventInstanceForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'recurring_events_orphaned_instances';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * Construct an OrphanedEventInstanceForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler service.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, ModuleHandler $module_handler) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $instances = $this->getOrphanedInstances();
    $count = count($instances);
    if (!empty($instances)) {
      foreach ($instances as $instance) {
        $this->moduleHandler->invokeAll('recurring_events_pre_delete_instance', [$instance]);
        $instance->delete();
      }
    }
    $this->messenger()->addMessage($this->t('Successfully deleted @count instance(s).', [
      '@count' => $count,
    ]));
  }

  /**
   * Define the form used for EventInstance settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $instances = $this->getOrphanedInstances();

    $rows = [];
    $header = ['Instance ID', 'Series ID', 'Title', 'Actions'];
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No orphaned instances found.'),
    ];

    if (!empty($instances)) {
      foreach ($instances as $instance) {
        $rows[] = [
          $instance->id(),
          $instance->get('eventseries_id')->first()->target_id ?? $this->t('N/A'),
          $instance->label() ?? $this->t('Unable to determine'),
          new FormattableMarkup('@view_link | @delete_link', [
            '@view_link' => $instance->toLink('View')->toString(),
            '@delete_link' => $instance->toLink('Delete', 'delete-form')->toString(),
          ]),
        ];
      }

      $form['table']['#rows'] = $rows;

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Orphaned Instances'),
      ];
    }

    return $form;
  }

  /**
   * Get all the orphaned instances.
   *
   * @return EventInstance[]|array
   *   An array of event instance entities, or an empty array.
   */
  protected function getOrphanedInstances() {
    $query = $this->database
      ->select('eventinstance_field_data', 'efd')
      ->fields('efd', ['id']);
    $query->leftJoin('eventseries', 'es', 'efd.eventseries_id = es.id');
    $instances = $query->condition('es.id', NULL, 'IS NULL')
      ->accessCheck(FALSE)
      ->execute()
      ->fetchCol();

    if (!empty($instances)) {
      $instances = $this->entityTypeManager->getStorage('eventinstance')->loadMultiple($instances);
    }
    return $instances;
  }

}
