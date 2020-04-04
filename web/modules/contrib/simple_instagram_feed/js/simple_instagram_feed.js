(function (Drupal, drupalSettings, $) {
  'use strict';
  Drupal.behaviors.simple_instagram_feed = {
    attach: function (context, settings) {
      $(window).on('load', function () {

        var instagram_username = drupalSettings.simple_instagram_feed.simple_instagram_block.instagram_username;
        var display_profile = drupalSettings.simple_instagram_feed.simple_instagram_block.display_profile;
        var display_biography = drupalSettings.simple_instagram_feed.simple_instagram_block.display_biography;
        var items = drupalSettings.simple_instagram_feed.simple_instagram_block.items;
        var styling = (drupalSettings.simple_instagram_feed.simple_instagram_block.styling === 'true' ? true : false);
        var items_per_row = drupalSettings.simple_instagram_feed.simple_instagram_block.items_per_row;
        var block_instance = drupalSettings.simple_instagram_feed.simple_instagram_block.block_instance;
        var block_target = (drupalSettings.simple_instagram_feed.simple_instagram_block.block_without_id === 'true' ?
          '.block-' + block_instance : '#block-' + block_instance);

        var settings = {
          username: instagram_username,
          container: block_target + ' .instagram-feed',
          display_profile: display_profile,
          display_biography: display_biography,
          display_gallery: true,
          callback: null,
          styling: styling,
          items: items,
          margin: 0.25
        };

        if (styling) {
          settings.items_per_row = items_per_row;
        }

        $.instagramFeed(settings);
      });
    }
  };
})(Drupal, drupalSettings, jQuery);
