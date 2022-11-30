<?php

namespace Drupal\recurring_events_registration\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory
  ) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $config = $this->configFactory->get('recurring_events_registration.registrant.config');

    // Change path '/user/login' to '/login'.
    if ($route = $collection->get('entity.registrant.latest_version')) {
      $route->setRequirement('eventinstance', '\d+');
      $option = $route->getOption('parameters');
      $option['eventinstance'] = [
        'type' => 'entity:eventinstance',
        'load_latest_revision' => TRUE,
      ];
      $route->setOption('parameters', $option);
    }

    // Render pages using the admin theme if this setting is enabled.
    if ($config->get('use_admin_theme')) {
      $routeNames = [
        'entity.registrant.collection',
        'entity.registrant.canonical',
        'entity.registrant.add_form',
        'entity.registrant.edit_form',
        'entity.registrant.delete_form',
        'entity.registrant.resend_form',
        'entity.registrant.anon_edit_form',
      ];

      foreach ($routeNames as $routeName) {
        if ($route = $collection->get($routeName)) {
          $route->setOption('_admin_route', TRUE);
        }
      }
    }
  }

}
