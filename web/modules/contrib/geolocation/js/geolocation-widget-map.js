/**
 * @file
 * Javascript for the Geolocation map widget.
 */

/**
 * @typedef {Object} WidgetSettings
 *
 * @property {String} brokerImportPath
 */

(function (Drupal) {
  "use strict";

  /**
   * Generic widget behavior.
   *
   * @type {Drupal~behavior}
   * @type {Object} drupalSettings.geolocation
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches Geolocation widget functionality to relevant elements.
   */
  Drupal.behaviors.geolocationWidget = {
    /**
     * @param {Document} context
     * @param {Object} drupalSettings
     * @param {Object} drupalSettings.geolocation
     * @param {WidgetSettings[]} drupalSettings.geolocation.widgetSettings
     */
    attach: function (context, drupalSettings) {
      context.querySelectorAll(".geolocation-map-widget").forEach((form) => {
        if (form.classList.contains("processed")) {
          return;
        }
        form.classList.add("processed");

        let id = form.getAttribute("id") ?? null;
        if (!id) {
          return;
        }

        let widgetSettings = drupalSettings.geolocation.widgetSettings[id] ?? null;
        if (!widgetSettings) {
          return;
        }

        import(widgetSettings.brokerImportPath).then((brokerImport) => {
          new brokerImport.default(id, widgetSettings);
        });
      });
    },
  };
})(Drupal);
