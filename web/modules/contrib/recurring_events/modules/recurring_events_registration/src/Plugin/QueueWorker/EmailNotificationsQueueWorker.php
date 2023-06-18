<?php

namespace Drupal\recurring_events_registration\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Defines 'recurring_events_registration_email_notifications_queue_worker' queue worker.
 *
 * @QueueWorker(
 *   id = "recurring_events_registration_email_notifications_queue_worker",
 *   title = @Translation("Email Notifications Queue Worker"),
 *   cron = {"time" = 30}
 * )
 */
class EmailNotificationsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new LocaleTranslation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    if (empty($item->key) || empty($item->to) || (empty($item->params['subject']) && empty($item->params['body']))) {
      return;
    }
    // All this worker has to do is to send the email.
    // The Subject and Body already have to be included in the `$item`.
    $this->mailManager->mail('recurring_events_registration', $item->key, $item->to, $this->languageManager->getDefaultLanguage()->getId(), $item->params);
  }

}
