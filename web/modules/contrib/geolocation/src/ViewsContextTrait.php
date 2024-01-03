<?php

namespace Drupal\geolocation;

use Drupal\views\Plugin\views\display\DisplayPluginInterface;

/**
 * Trait ViewsContext.
 */
trait ViewsContextTrait {

  /**
   * Get display handler from context.
   *
   * @param array $context
   *   Context.
   *
   * @return \Drupal\views\Plugin\views\display\DisplayPluginInterface|null
   *   Display handler or FALSE.
   */
  protected static function getViewsDisplayHandler(array $context = []): ?DisplayPluginInterface {
    if (!empty($context['views_style'])) {
      if (!is_object($context['views_style'])) {
        return NULL;
      }
      if (is_subclass_of($context['views_style'], 'Drupal\views\Plugin\views\style\StylePluginBase')) {
        return $context['views_style']->displayHandler;
      }
      if (is_subclass_of($context['views_style'], 'Drupal\views\Plugin\views\HandlerBase')) {
        return $context['views_style']->displayHandler;
      }
    }
    elseif (!empty($context['views_filter'])) {
      if (!is_object($context['views_filter'])) {
        return NULL;
      }
      return $context['views_filter']->displayHandler;
    }
    elseif (!empty($context['views_field'])) {
      if (!is_object($context['views_field'])) {
        return NULL;
      }
      return $context['views_field']->displayHandler;
    }

    return NULL;
  }

}
