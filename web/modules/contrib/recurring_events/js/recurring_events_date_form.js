/**
 * @file
 * Javascript functionality for the included/excluded date forms.
 */

(function ($, once) {
  'use strict';

  /**
   * Set end date for excluded and included dates to be the same as the start.
   */
  Drupal.behaviors.recurring_events_excluded_included_config_dates = {
    attach: function (context, settings) {

      $(once('edit_recurring_events_excluded_included_config_dates', $('#edit-start'))).on('change', function (e) {
        if ($('#edit-end').val() == '') {
          $('#edit-end').val($(this).val());
        }
      });
    }
  };

}(jQuery, once));
