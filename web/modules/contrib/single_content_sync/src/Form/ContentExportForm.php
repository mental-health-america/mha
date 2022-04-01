<?php

namespace Drupal\single_content_sync\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\single_content_sync\ContentExporterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to export content.
 *
 * @package Drupal\single_content_sync\Form
 */
class ContentExportForm extends FormBase {

  /**
   * The content exporter service.
   *
   * @var \Drupal\single_content_sync\ContentExporterInterface
   */
  protected $contentExporter;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ContentExportForm constructor.
   *
   * @param \Drupal\single_content_sync\ContentExporterInterface $content_exporter
   *   The content exporter service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ContentExporterInterface $content_exporter, FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager) {
    $this->contentExporter = $content_exporter;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('single_content_sync.exporter'),
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'single_content_sync_export_form';
  }

  /**
   * Download file automatically when it requested.
   *
   * @param array $form
   *   The form array.
   */
  protected function handleAutoFileDownload(array &$form) {
    // Don't check for file downloads if this is a submit request.
    if ($this->getRequest()->getMethod() !== 'POST') {
      if ($filename = $this->getRequest()->query->get('file')) {
        $files = $this->entityTypeManager->getStorage('file')
          ->loadByProperties(['filename' => $filename]);
        /** @var \Drupal\file\FileInterface $file */
        $file = reset($files);
        if (file_exists($file->getFileUri())) {
          $download_url = Url::fromRoute('single_content_sync.file_download', [], [
            'query' => ['file' => $filename],
            'absolute' => TRUE,
          ])->toString();

          $form['#attached']['html_head'][] = [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'http-equiv' => 'refresh',
                'content' => '0; url=' . $download_url,
              ],
            ],
            'single_content_sync_export_download',
          ];
        }
        // If the file does not exist, something went wrong.
        else {
          $this->messenger()->addError($this->t('The export file could not be found, please try again.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->handleAutoFileDownload($form);

    $parameters = $this->getRouteMatch()->getParameters();
    $entity = $parameters->getIterator()->current();
    $export_in_yaml = $this->contentExporter->doExportToYml($entity);

    $form['output'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exported content'),
      '#attributes' => [
        'data-yaml-editor' => 'true',
      ],
      '#value' => $export_in_yaml,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['download_zip'] = [
      '#type' => 'submit',
      '#name' => 'download_zip',
      '#button_type' => 'primary',
      '#value' => $this->t('Download as a zip with all assets'),
    ];

    $form['actions']['download_file'] = [
      '#type' => 'submit',
      '#name' => 'download_file',
      '#value' => $this->t('Download as a file'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $file_name = '';

    switch ($button['#name']) {
      case 'download_file':
        $output = $form_state->getValue('output');
        $content = Yaml::decode($output);
        $directory = 'public://export';
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $file = file_save_data($output, "{$directory}/{$content['uuid']}.yml", FileSystemInterface::EXISTS_REPLACE);
        $file->setTemporary();
        $file->save();

        $file_name = $file->getFilename();
        $url = Url::fromRoute('single_content_sync.file_download', [], [
          'query' => ['file' => $file_name],
          'absolute' => TRUE,
        ]);
        $message = $this->t('Your download should begin now. If it does not start, download the file @link.', [
          '@link' => Link::fromTextAndUrl($this->t('here'), $url)->toString(),
        ]);
        $this->messenger()->addStatus($message);
        break;

      case 'download_zip':
        $this->messenger()->addWarning($this->t('This is not ready yet, coming soon!'));
        break;
    }

    $form_state->setRedirect($this->getRouteMatch()->getRouteName(), $this->getRouteMatch()->getRawParameters()->all(), [
      'query' => [
        'file' => $file_name,
      ],
    ]);
  }

}
