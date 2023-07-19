<?php

namespace Drupal\recurring_events_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\recurring_events_registration\NotificationService;
use Drupal\recurring_events_registration\RegistrationCreationService;
use Drupal\Core\Extension\ModuleHandler;

/**
 * Provides a form for managing registration settings.
 *
 * @ingroup recurring_events_registration
 */
class RegistrantSettingsForm extends ConfigFormBase {

  /**
   * The registration notification service.
   *
   * @var \Drupal\recurring_events_registration\NotificationService
   */
  protected $notificationService;

  /**
   * The registration creation service.
   *
   * @var \Drupal\recurring_events_registration\RegistrationCreationService
   */
  protected $creationService;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Constructs a RegistrantSettingsForm object.
   *
   * @param \Drupal\recurring_events_registration\NotificationService $notification_service
   *   The registration notification service.
   * @param \Drupal\recurring_events_registration\RegistrationCreationService $creation_service
   *   The registration creation service.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder.
   */
  public function __construct(
    NotificationService $notification_service,
    RegistrationCreationService $creation_service,
    ModuleHandler $module_handler,
    RouteBuilderInterface $route_builder
  ) {
    $this->notificationService = $notification_service;
    $this->creationService = $creation_service;
    $this->moduleHandler = $module_handler;
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recurring_events_registration.notification_service'),
      $container->get('recurring_events_registration.creation_service'),
      $container->get('module_handler'),
      $container->get('router.builder')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'registrant_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recurring_events_registration.registrant.config'];
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('recurring_events_registration.registrant.config')
      ->set('show_capacity', $form_state->getValue('show_capacity'))
      ->set('insert_redirect_choice', $form_state->getValue('insert_redirect_choice'))
      ->set('insert_redirect_other', $form_state->getValue('insert_redirect_other'))
      ->set('use_admin_theme', $form_state->getValue('use_admin_theme'))
      ->set('limit', $form_state->getValue('limit'))
      ->set('date_format', $form_state->getValue('date_format'))
      ->set('title', $form_state->getValue('title'))
      ->set('successfully_registered', $form_state->getValue('successfully_registered'))
      ->set('successfully_registered_waitlist', $form_state->getValue('successfully_registered_waitlist'))
      ->set('successfully_updated', $form_state->getValue('successfully_updated'))
      ->set('successfully_updated_waitlist', $form_state->getValue('successfully_updated_waitlist'))
      ->set('already_registered', $form_state->getValue('already_registered'))
      ->set('registration_closed', $form_state->getValue('registration_closed'))
      ->set('email_notifications', $form_state->getValue('email_notifications'))
      ->set('email_notifications_queue', $form_state->getValue('email_notifications_queue'));

    if ($config->getOriginal('use_admin_theme') != $config->get('use_admin_theme')) {
      $this->routeBuilder->setRebuildNeeded();
    }

    $notification_types = [];
    $this->moduleHandler->alter('recurring_events_registration_notification_types', $notification_types);

    $notification_config = [];
    foreach ($notification_types as $type => $notification) {
      $notification_config[$type] = [
        'enabled' => $form_state->getValue($type . '_enabled'),
        'subject' => $form_state->getValue($type . '_subject'),
        'body' => $form_state->getValue($type . '_body'),
      ];
    }

    $config->set('notifications', $notification_config);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Defines the settings form for Registrant entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('recurring_events_registration.registrant.config');

    $form['process'] = [
      '#type' => 'details',
      '#title' => $this->t('Registration Form'),
      '#open' => TRUE,
    ];

    $form['process']['show_capacity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Capacity?'),
      '#description' => $this->t('When users are registering for events, show the available capacity?'),
      '#default_value' => $config->get('show_capacity'),
    ];

    $form['process']['insert_redirect_choice'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose where registrant form redirects'),
      '#default_value' => $config->get('insert_redirect_choice'),
      '#options' => [
        'current' => $this->t('Current page where form appears'),
        'instance' => $this->t('Event instance page'),
        'series' => $this->t('Event series page'),
        'other' => $this->t('Custom URL'),
      ],
    ];

    $form['process']['insert_redirect_other'] = [
      '#type' => 'url',
      '#title' => $this->t('Type custom URL here'),
      '#default_value' => $config->get('insert_redirect_other'),
      '#states' => [
        'visible' => [
          ':input[name="insert_redirect_choice"]' => ['value' => 'other'],
        ],
        'required' => [
          ':input[name="insert_redirect_choice"]' => ['value' => 'other'],
        ],
      ],
    ];

    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('Registrant Display'),
      '#open' => TRUE,
    ];

    $form['display']['use_admin_theme'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the administration theme when managing registrations'),
      '#description' => $this->t('Control which roles can "View the administration theme" on the <a href=":permissions">Permissions page</a>.', [
        ':permissions' => Url::fromRoute('user.admin_permissions')->toString(),
      ]),
      '#default_value' => $config->get('use_admin_theme'),
    ];

    $form['display']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Registrant Items'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the number of items to show per page in the default registrant listing table.'),
      '#default_value' => $config->get('limit'),
    ];

    $php_date_url = Url::fromUri('https://secure.php.net/manual/en/function.date.php');
    $php_date_link = Link::fromTextAndUrl($this->t('PHP date/time format'), $php_date_url);

    $form['display']['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Registrant Date Format'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the @link used when listing registrants. Default is F jS, Y h:iA.', [
        '@link' => $php_date_link->toString(),
      ]),
      '#default_value' => $config->get('date_format'),
    ];

    $registrant_tokens = $this->creationService->getAvailableTokens(['registrant']);

    $form['display']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Registrant Title'),
      '#required' => TRUE,
      '#description' => $this->t('Enter the format for the title field', [
        '@link' => $php_date_link->toString(),
      ]),
      '#default_value' => $config->get('title'),
    ];

    $form['display']['tokens'] = $registrant_tokens;

    $form['messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Registration Messages'),
      '#open' => TRUE,
    ];

    $form['messages']['successfully_registered'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Successful Registration'),
      '#description' => $this->t('This message will show in the message area when a user successfully registers for an event.'),
      '#default_value' => $config->get('successfully_registered'),
    ];

    $form['messages']['successfully_registered_waitlist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Successful Registration (Waitlist)'),
      '#description' => $this->t("This message will show in the message area when a user successfully registers for an event's waitlist."),
      '#default_value' => $config->get('successfully_registered_waitlist'),
    ];

    $form['messages']['successfully_updated'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Successful Update'),
      '#description' => $this->t('This message will show in the message area when a user successfully updates a registration for an event.'),
      '#default_value' => $config->get('successfully_updated'),
    ];

    $form['messages']['successfully_updated_waitlist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Successful Update (Waitlist)'),
      '#description' => $this->t("This message will show in the message area when a user successfully updates a registration for an event's waitlist."),
      '#default_value' => $config->get('successfully_updated_waitlist'),
    ];

    $form['messages']['already_registered'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Duplicate Registration'),
      '#description' => $this->t('This message will show in the message area when a user tries to register a second time for the same event.'),
      '#default_value' => $config->get('already_registered'),
    ];

    $form['messages']['registration_closed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Registration Window Closed'),
      '#description' => $this->t('This message will show in the message area when a user tries to register for an event for which registrations are closed.'),
      '#default_value' => $config->get('registration_closed'),
    ];

    $form['messages']['tokens'] = $registrant_tokens;

    $form['notifications'] = [
      '#type' => 'details',
      '#title' => $this->t('Email Notifications'),
      '#open' => TRUE,
    ];

    $form['notifications']['email_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send Email Notifications?'),
      '#description' => $this->t('Send email notifications during registration or event updates?'),
      '#default_value' => $config->get('email_notifications'),
    ];

    $form['notifications']['email_notifications_queue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send Email Notifications using a queue?'),
      '#description' => $this->t('Email notifications can be added to a queue to be processed on each cron run. This could be beneficial if you have a large number of registrants to your series and/or instances, to prevent the system from crashing when sending notifications to all of those recipients at once. Depending on your PHP configuration, your system may hit time and memory limits when sending notifications to a massive list of registered people. In particular, the notification types below are designed to send an email to all registrants of a specific series or instance, so those might be the problematic ones:<br>
        <ul>
          <li>Instance Deletion Notification</li>
          <li>Series Deletion Notification</li>
          <li>Instance Modification Notification</li>
          <li>Series Modification Notification</li>
          <li>Registration Reminder</li>
        </ul>
        <br>If you check this option, emails corresponding to those notification types will be queued and a queue worker will process as many items as it can in 30 seconds on each cron run.<br><br>
        How long it takes for the queued notification list to be fully processed depends on three factors:
        <ol>
          <li>The number of notifications in the queue</li>
          <li>How often Drupal cron runs</li>
          <li>The number of seconds used by the queue worker on each cron run to process the items (it was set to 30)</li>
        </ol>
        <br>Notification types that are sent to only one recipient continue to be sent immediately as soon as the trigger action occurs, regardless of this setting, namely:<br>
        <ul>
          <li>Registration Notification</li>
          <li>Waitlist Notification</li>
          <li>Promotion Notification</li>
        </ul>
        <br><b>Important note for developers:</b><br>
          When the notification types that are not queued (this is always the case for the above-mentioned notification types, which are sent to a single recipient. It will also be the case for all notification types if you uncheck this option) the registrant entity will be passed in the params to <b><em>hook_mail()</em></b> and <b><em>hook_mail_alter()</em></b>. It will be accessible through <b><em>$params[\'registrant\']</em></b> in the first case and <b><em>$message[\'params\'][\'registrant\']</em></b> in the second.<br>
          However, when notifications are queued it is not possible to pass the registrant entity to mail hooks, since it is likely that the entity no longer exists by the moment the queue worker takes action and sends the email.<br>
          <b>That is why we highly discourage the use of the registrant entity in mail hooks</b>. To maintain consistency between the two models (queued and non-queued messages), we encourage developers to make use of the <b><em>hook_recurring_events_registration_message_params_alter()</em></b> to define any value in the params that might be needed to perform any logic on the mail hooks.<br>
          See more detail about that hook in <em>recurring_events_registration.api.php</em>.
      '),
      '#default_value' => $config->get('email_notifications_queue'),
      '#states' => [
        'visible' => [
          'input[name="email_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['notifications']['emails'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Emails'),
      '#states' => [
        'visible' => [
          'input[name="email_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $tokens = $this->notificationService->getAvailableTokens();

    $notification_types = [];
    $this->moduleHandler->alter('recurring_events_registration_notification_types', $notification_types);
    $notification_config = $config->get('notifications');

    foreach ($notification_types as $type => $notification) {
      $form['notifications'][$type] = [
        '#type' => 'details',
        '#title' => $notification['name'],
        '#open' => TRUE,
        '#group' => 'emails',
      ];
      $form['notifications'][$type][$type . '_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $notification['name'],
        '#description' => $notification['description'],
        '#default_value' => $notification_config[$type]['enabled'],
      ];
      $form['notifications'][$type][$type . '_subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $notification_config[$type]['subject'],
        '#maxlength' => 180,
        '#states' => [
          'visible' => [
            'input[name="' . $type . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['notifications'][$type][$type . '_body'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Body'),
        '#default_value' => $notification_config[$type]['body'],
        '#rows' => 15,
        '#states' => [
          'visible' => [
            'input[name="' . $type . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $form['notifications'][$type]['tokens'] = [
        '#type' => 'container',
        'tokens' => $tokens,
        '#states' => [
          'visible' => [
            'input[name="' . $type . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

}
