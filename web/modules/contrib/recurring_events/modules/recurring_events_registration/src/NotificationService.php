<?php

namespace Drupal\recurring_events_registration;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Utility\Token;
use Drupal\recurring_events_registration\Entity\RegistrantInterface;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Provides a service with helper functions to facilitate notifications.
 */
class NotificationService {

  /**
   * The translation interface.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  private $translation;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The registration creation service.
   *
   * @var \Drupal\recurring_events_registration\RegistrationCreationService
   */
  protected $creationService;

  /**
   * The registrant entity.
   *
   * @var \Drupal\recurring_events_registration\Entity\RegistrantInterface
   */
  protected $entity;

  /**
   * The email key.
   *
   * @var string
   */
  protected $key;

  /**
   * The email subject.
   *
   * @var string
   */
  protected $subject;

  /**
   * The email message.
   *
   * @var string
   */
  protected $message;

  /**
   * The from address.
   *
   * @var string
   */
  protected $from;

  /**
   * The config name.
   *
   * @var string
   */
  protected $configName;

  /**
   * Whether this is a custom or configure email.
   *
   * @var bool
   */
  protected $custom = FALSE;

  /**
   * The update fetch queue.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation interface.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler service.
   * @param \Drupal\recurring_events_registration\RegistrationCreationService $creation_service
   *   The registration creation service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(TranslationInterface $translation, ConfigFactory $config_factory, LoggerChannelFactoryInterface $logger, Messenger $messenger, Token $token, ModuleHandler $module_handler, RegistrationCreationService $creation_service, QueueFactory $queue_factory) {
    $this->translation = $translation;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger->get('recurring_events_registration');
    $this->messenger = $messenger;
    $this->token = $token;
    $this->moduleHandler = $module_handler;
    $this->creationService = $creation_service;
    $this->configName = 'recurring_events_registration.registrant.config';
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('token'),
      $container->get('module_handler'),
      $container->get('recurring_events_registration.creation_service'),
      $container->get('queue')
    );
  }

  /**
   * Set the registrant entity.
   *
   * @param \Drupal\recurring_events_registration\Entity\RegistrantInterface $registrant
   *   The registrant entity.
   *
   * @return $this
   *   The NotificationService object.
   */
  public function setEntity(RegistrantInterface $registrant) {
    $this->entity = $registrant;
    return $this;
  }

  /**
   * Set the email key.
   *
   * @param string $key
   *   The email key to use.
   *
   * @return $this
   */
  public function setKey($key) {
    $this->key = $key;
    if ($this->key === 'custom') {
      $this->custom = TRUE;
    }
    return $this;
  }

  /**
   * Set the email subject.
   *
   * @param string $subject
   *   The email subject line.
   *
   * @return $this
   */
  public function setSubject($subject) {
    $this->subject = $subject;
    return $this;
  }

  /**
   * Set the email message.
   *
   * @param string $message
   *   The email message.
   *
   * @return $this
   */
  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  /**
   * Set the email from address.
   *
   * @param string $from
   *   The from email address.
   *
   * @return $this
   */
  public function setFrom($from) {
    $this->from = $from;
    return $this;
  }

  /**
   * Set the config name.
   *
   * @param string $name
   *   The name of the config value to use.
   *
   * @return $this
   */
  public function setConfigName($name) {
    $this->configName = $name;
    return $this;
  }

  /**
   * Get the key.
   *
   * @return string|bool
   *   The key, or FALSE if not set.
   */
  public function getKey() {
    if (empty($this->key)) {
      $this->messenger->addError($this->translation->translate('No key defined for @module notifications.', [
        '@module' => 'recurring_events_registration',
      ]));
      $this->loggerFactory->error('No key defined @module notifications. Call @function before proceding.', [
        '@module' => 'recurring_events_registration',
        '@function' => 'NotificationService::setKey()',
      ]);
      return FALSE;
    }
    return $this->key;
  }

  /**
   * Get the config name.
   *
   * @return string
   *   The name of the config element.
   */
  protected function getConfigName() {
    if (empty($this->configName)) {
      $this->messenger->addError($this->translation->translate('No config name defined for @module notifications.', [
        '@module' => 'recurring_events_registration',
      ]));
      $this->loggerFactory->error('No config name defined for @module notifications. Call @function before proceding.', [
        '@module' => 'recurring_events_registration',
        '@function' => 'NotificationService::setConfigName()',
      ]);
      return FALSE;
    }
    return $this->configName;
  }

  /**
   * Retrieve config value.
   *
   * @var string $name
   *   The name of the config value to retrieve
   *
   * @return string|bool
   *   Return the config value, or FALSE if not set.
   */
  protected function getConfigValue($name) {
    $value = FALSE;
    $notifications = $this->configFactory->get($this->getConfigName())->get('notifications');
    if (!is_null($notifications[$this->key][$name])) {
      $value = $notifications[$this->key][$name];
    }

    return $value;
  }

  /**
   * Get the from address.
   *
   * @return string
   *   The from address.
   */
  public function getFrom() {
    $key = $this->getKey();
    if ($key) {
      $from = $this->from;
      if (empty($from)) {
        $from = $this->configFactory->get('system.site')->get('mail');
        $this->setFrom($from);
      }

      if (empty($from)) {
        $this->messenger->addError($this->translation->translate('No default from address configured. Please check the system.site mail config.'));
        return '';
      }
      return $from;
    }
    return '';
  }

  /**
   * Check notification is enabled.
   *
   * @return bool
   *   Returns TRUE if enabled, FALSE otherwise.
   */
  public function isEnabled() {
    $key = $this->getKey();
    if ($this->custom) {
      return TRUE;
    }
    if ($key) {
      return (bool) $this->getConfigValue('enabled');
    }
    return FALSE;
  }

  /**
   * Get the email subject.
   *
   * @param bool $parse_tokens
   *   Whether or not to parse out the tokens.
   *
   * @return string
   *   The email subject line.
   */
  public function getSubject($parse_tokens = TRUE) {
    $key = $this->getKey();
    if ($key) {
      $subject = $this->getConfigValue('subject');

      if (empty($subject)) {
        $this->messenger->addError($this->translation->translate('No default subject configured for @key emails in @config_name.', [
          '@key' => $key,
          '@config_name' => $this->getConfigName(),
        ]));
        return '';
      }

      if ($parse_tokens) {
        return $this->parseTokenizedString($subject);
      }
      return $subject;
    }
    return '';
  }

  /**
   * Get the email message.
   *
   * @param bool $parse_tokens
   *   Whether or not to parse out the tokens.
   *
   * @return string
   *   The email message.
   */
  public function getMessage($parse_tokens = TRUE) {
    $key = $this->getKey();
    if ($key) {
      $message = $this->getConfigValue('body');

      if (empty($message)) {
        $this->messenger->addError($this->translation->translate('No default body configured for @key emails in @config_name.', [
          '@key' => $key,
          '@config_name' => $this->getConfigName(),
        ]));
        return '';
      }

      if ($parse_tokens) {
        return $this->parseTokenizedString($message);
      }
      return $message;
    }
    return '';
  }

  /**
   * Parse a tokenized string.
   *
   * @var string $string
   *   The string to parse.
   *
   * @return string
   *   The parsed string.
   */
  public function parseTokenizedString($string) {
    // #3272196 for some reason the Registrant entity is sometimes null. So here
    // we check first to avoid throwing PHP notices.
    if (empty($this->entity)) {
      return $string;
    }

    $data = [
      'registrant' => $this->entity,
      'eventinstance' => $this->entity ? $this->entity->getEventInstance() : NULL,
      'eventseries' => $this->entity ? $this->entity->getEventSeries() : NULL,
    ];
    // Double token replace to allow for global token replacements containing
    // tokens themselves.
    return $this->token->replace($this->token->replace($string, $data), $data);
  }

  /**
   * Get available tokens form element.
   *
   * @return array
   *   A render array to render on the site.
   */
  public function getAvailableTokens() {
    $relevant_tokens = [
      'eventseries',
      'eventinstance',
      'registrant',
    ];

    return $this->creationService->getAvailableTokens($relevant_tokens);
  }

  /**
   * Adds an email notification to be sent later by the Queue Worker.
   */
  public function addEmailNotificationToQueue($key, RegistrantInterface $registrant) {
    $config = $this->configFactory->get('recurring_events_registration.registrant.config');
    $send_email = $config->get('email_notifications');
    $send_email_key = $config->get('notifications' . '.' . $key . '.enabled');

    // Modify $send_email if necessary.
    if ($registrant instanceof RegistrantInterface) {
      $this->moduleHandler->alter('recurring_events_registration_send_notification', $send_email, $registrant);
    }

    if ($send_email && $send_email_key) {
      // We need to get the parsed email subject and message (after token
      // replacement) to add them to the `$item` that will be queued. We are
      // not adding the `$registrant` to the `$item`, since in the queue worker
      // we cannot rely on operations over the `$registrant` or its parent
      // instance or series, since at that point those entities might have been
      // deleted. There are some operations and notification types that require
      // the `$registrant`to be deleted, for example: the notifications
      // corresponding to the keys 'series_modification_notification' and
      // 'instance_deletion_notification'.
      // @see recurring_events_registration_recurring_events_save_pre_instances_deletion()
      // @see recurring_events_registration_recurring_events_pre_delete_instance()
      $this->setKey($key)->setEntity($registrant);
      $subject = $this->getSubject();
      $message = $this->getMessage();
      $from = $this->getFrom();

      // Create the item to be added to the queue.
      $item = new \stdClass();
      $item->key = $key;
      $item->to = $registrant->email->value;

      $params = [
        'subject' => $subject,
        'body' => $message,
        'from' => $from,
      ];
      // Allow modules to add data to the `$params`. Developers can get data 
      // from `$registrant`. Those `$params` can be used later as the
      // `$params` in `hook_mail()` and `$message['params']` in
      // `hook_mail_alter()`.
      // In queued messages, we are not passing the `$registrant` entity as a
      // param (unlike it is being done in non-queued messages
      // `recurring_events_registration_send_notification`), because the entity
      // could no longer exist when the queue worker takes action and sends the
      // email. For example, as mentioned above in another comment, there are
      // some notification types that require the `$registrant` to be deleted
      // as part of the same operation that generates the notification, for
      // example: the notifications corresponding to the keys
      // 'series_modification_notification' and
      // 'instance_deletion_notification'.
      // Therefore, those entities won't be available in the queue worker.
      // It would be unsafe to try to access a registrant for a queued message
      // via `$params['registrant']` in `hook_mail()` or
      // `$message['params']['registrant']` in `hook_mail_alter()`.
      // We encourage developers to make use of the
      // `hook_recurring_events_registration_message_params_alter()` to define
      // any value in the params that could be necessary to perform any logic
      // in the mail hooks (ideally scalar values, custom arrays or custom
      // objects. No loaded entities and no configuration objects, since those
      // could have changed or been deleted by the moment the queue worker is
      // called), those values will be added to the queued item and will be
      // available in the queue worker when it processes the item and in the
      // mail hooks later.
      // @see recurring_events_registration_recurring_events_save_pre_instances_deletion()
      // @see recurring_events_registration_recurring_events_pre_delete_instance
      $this->moduleHandler->alter('recurring_events_registration_message_params', $params, $registrant);
      $item->params = $params;

      // Add the item to the queue.
      $queue = $this->queueFactory->get('recurring_events_registration_email_notifications_queue_worker');
      $queue->createItem($item);
    }
  }

}
