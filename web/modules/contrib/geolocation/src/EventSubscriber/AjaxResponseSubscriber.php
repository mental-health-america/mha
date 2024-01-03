<?php

namespace Drupal\geolocation\EventSubscriber;

use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\geolocation\Plugin\views\style\CommonMap;

/**
 * Response subscriber to handle AJAX responses.
 */
class AjaxResponseSubscriber implements EventSubscriberInterface {

  /**
   * Renders the ajax commands right before preparing the result.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // Only alter views ajax responses.
    if (!($response instanceof ViewAjaxResponse)) {
      return;
    }

    $view = $response->getView();

    if (
      !is_a($view->getStyle(), CommonMap::class)
      && !array_filter($view->display_handler->getAttachedDisplays(), fn($display_id) =>
       is_a($view->displayHandlers->get($display_id)->getPlugin('style'), CommonMap::class)
      )
    ) {
      // Neither this display nor any attachment is a Common Map. Nothing to do.
      return;
    }

    foreach ($response->getCommands() as $delta => &$command) {
      // Stop the view from scrolling to the top of the page.
      if (
        $command['command'] === 'viewsScrollTop'
        && $event->getRequest()->query->get('page', FALSE) === FALSE
      ) {
        unset($response->getCommands()[$delta]);
        continue;
      }

      if (
        $command['command'] == 'insert'
        && $command['method'] == 'replaceWith'
        && str_contains($command['data'], 'geolocation-map-wrapper')
      ) {
        $command['command'] = 'geolocation';
        $command['method'] = 'replaceCommonMapView';
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => [['onResponse']]];
  }

}
