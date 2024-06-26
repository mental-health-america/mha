<?php

namespace Drupal\recurring_events\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Sets the _admin_route for specific recurring events related routes.
 */
class RecurringEventsAdminRouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * Constructs a new RecurringEventsAdminRouteSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RouteBuilderInterface $router_builder) {
    $this->configFactory = $config_factory;
    $this->routerBuilder = $router_builder;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($this->configFactory->get('node.settings')->get('use_admin_theme')) {
      foreach ($collection->all() as $route) {
        if ($route->hasOption('_recurring_events_operation_route')) {
          $route->setOption('_admin_route', TRUE);
        }
      }
    }
  }

  /**
   * Rebuilds the router when node.settings:use_admin_theme is changed.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The config crud event that gets fired.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    if ($event->getConfig()->getName() === 'node.settings' && $event->isChanged('use_admin_theme')) {
      $this->routerBuilder->setRebuildNeeded();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    return $events;
  }

}
