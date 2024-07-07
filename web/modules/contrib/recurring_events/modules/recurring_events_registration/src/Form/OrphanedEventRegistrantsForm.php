<?php

namespace Drupal\recurring_events_registration\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for handling orphaned registrants.
 *
 * @ingroup recurring_events
 */
class OrphanedEventRegistrantsForm extends FormBase {

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
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'recurring_events_registration_orphaned_registrants';
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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
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
    $registrants = $this->getOrphanedRegistrants();
    $count = count($registrants);
    if (!empty($registrants)) {
      foreach ($registrants as $registrant) {
        $registrant->delete();
      }
    }
    $this->messenger()->addMessage($this->t('Successfully deleted @count registrants(s).', [
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
    $registrants = $this->getOrphanedRegistrants();

    $rows = [];
    $header = ['Registrant ID', 'Series ID', 'Label', 'Actions'];
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No orphaned registrants found.'),
    ];

    if (!empty($registrants)) {
      foreach ($registrants as $registrant) {
        $rows[] = [
          $registrant->id(),
          $registrant->get('eventseries_id')->first()->target_id ?? $this->t('N/A'),
          $registrant->label(),
          new FormattableMarkup('@view_link | @delete_link', [
            '@view_link' => $registrant->toLink('View')->toString(),
            '@delete_link' => $registrant->toLink('Delete', 'delete-form')->toString(),
          ]),
        ];
      }

      $form['table']['#rows'] = $rows;

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Orphaned Registrants'),
      ];
    }

    return $form;
  }

  /**
   * Get all the orphaned registrants.
   *
   * @return \Drupal\recurring_events_registration\Entity\RegistrantInterface[]|array
   *   An array of event instance entities, or an empty array.
   */
  protected function getOrphanedRegistrants() {
    $query = $this->database
      ->select('registrant', 'r')
      ->fields('r', ['id']);
    $query->leftJoin('eventseries', 'es', 'r.eventseries_id = es.id');
    $query->leftJoin('eventinstance', 'ei', 'r.eventinstance_id = ei.id');
    $or_group = $query->orConditionGroup()
      ->condition('es.id', NULL, 'IS NULL')
      ->condition('ei.id', NULL, 'IS NULL');
    $registrants = $query->condition($or_group)
      ->execute()
      ->fetchCol();

    if (!empty($registrants)) {
      $registrants = $this->entityTypeManager->getStorage('registrant')->loadMultiple($registrants);
    }
    return $registrants;
  }

}
