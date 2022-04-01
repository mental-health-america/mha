<?php

namespace Drupal\single_content_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\single_content_sync\ContentImporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to import a content.
 *
 * @package Drupal\single_content_sync\Form
 */
class ContentImportForm extends FormBase {

  /**
   * The content importer service.
   *
   * @var \Drupal\single_content_sync\ContentImporterInterface
   */
  protected $contentImporter;

  /**
   * ContentImportForm constructor.
   *
   * @param \Drupal\single_content_sync\ContentImporterInterface $content_importer
   *   The content importer service.
   */
  public function __construct(ContentImporterInterface $content_importer) {
    $this->contentImporter = $content_importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('single_content_sync.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'single_content_sync_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#required' => TRUE,
      '#attributes' => [
        'data-yaml-editor' => 'true',
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $content = Yaml::decode($form_state->getValue('content'));

    if (!is_array($content)) {
      $form_state->setErrorByName('content', $this->t('YAML is not valid.'));
    }
    elseif (!isset($content['uuid']) || !isset($content['entity_type']) || !isset($content['bundle']) || !isset($content['base_fields']) || !isset($content['custom_fields'])) {
      $form_state->setErrorByName('content', $this->t('Content is not valid. Make sure there are uuid, entity_type, bundle, base_fields, and custom_fields properties.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $content = Yaml::decode($form_state->getValue('content'));

    // Import a content.
    $node = $this->contentImporter->doImport($content);

    $this->messenger()->addStatus($this->t('The content has been synced @link', [
      '@link' => $node->toLink()->toString(),
    ]));
  }

}
