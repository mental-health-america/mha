/**
 * @file
 * Provides AJAX functionality for Ultimenu blocks.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.ultimenu = Drupal.ultimenu || {};

  /**
   * Ultimenu utility functions for the ajaxified links, including main menu.
   *
   * @param {int} i
   *   The index of the current element.
   * @param {HTMLElement} elm
   *   The ultimenu HTML element.
   */
  function doUltimenuAjax(i, elm) {
    var $elm = $(elm);

    if (drupalSettings.ultimenu && drupalSettings.ultimenu.ajaxmw && window.matchMedia) {
      var mw = window.matchMedia('(max-device-width: ' + drupalSettings.ultimenu.ajaxmw + ')');
      if (mw.matches) {
        // Load all AJAX contents if so configured.
        // Alternatively trigger the AJAX only if the hamburger is clicked.
        $elm.find('.ultimenu__link[data-ultiajax-trigger]').each(function (i, item) {
          var $trigger = $(item);
          Drupal.ultimenu.executeAjax($trigger);
        });
        return;
      }
    }

    // Regular mobie/ desktop AJAX.
    $elm.off().on('mouseover click touchstart', '.ultimenu__link[data-ultiajax-trigger]', Drupal.ultimenu.triggerAjax.bind(Drupal.ultimenu));
  }

  /**
   * Attaches Ultimenu behavior to HTML element [data-ultiajax].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ultimenuAjax = {
    attach: function (context) {

      // Modifies functionality for any of the ajaxified ultimenus.
      $(context).find('[data-ultiajax]').once('ultiajax').each(doUltimenuAjax);
    }
  };

})(jQuery, Drupal, drupalSettings);
