(function (Drupal, drupalSettings, $) {
  'use strict';
  Drupal.behaviors.simple_instagram_feed = {
    attach: function (context, settings) {
      var simple_instagram_feed_array = Object.values(drupalSettings.simple_instagram_feed);
      for (var i = 0; i < simple_instagram_feed_array.length; i++) {
        var instagram_username = simple_instagram_feed_array[i].instagram_username;
        var display_profile = simple_instagram_feed_array[i].display_profile;
        var display_biography = simple_instagram_feed_array[i].display_biography;
        var items = simple_instagram_feed_array[i].items;
        var styling = (simple_instagram_feed_array[i].styling === 'true' ? true : false);
        var items_per_row = simple_instagram_feed_array[i].items_per_row;
        var block_instance = simple_instagram_feed_array[i].block_instance;
        var block_target = (simple_instagram_feed_array[i].block_without_id === 'true' ?
          '.block-' + block_instance : '#block-' + block_instance);
        block_target = block_target + ' .instagram-feed';
        var feed_settings = {
          username: instagram_username,
          container: block_target,
          display_profile: display_profile,
          display_biography: display_biography,
          display_gallery: true,
          callback: null,
          styling: styling,
          items: items,
          margin: 0.25
        };
        if (styling) {
          feed_settings.items_per_row = items_per_row;
        }
        $.instagramFeed(feed_settings);
      }
    }
  };
})(Drupal, drupalSettings, jQuery);
