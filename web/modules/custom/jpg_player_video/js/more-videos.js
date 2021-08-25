/**
 * @file
 * Behaviors for the "More video" field media items.
 */

'use strict';

(function (Drupal, $) {
  Drupal.behaviors.jpgMoreVideoMediaAjaxRerender = {
    attach(context, settings) {
      if (settings.jpgPlayerVideoMoreVideos !== undefined && settings.jpgPlayerVideoMoreVideos.ajaxEnabled) {
        let wrapperSelector = settings.jpgPlayerVideoMoreVideos.wrapperSelector;
        if (!wrapperSelector) {
          return;
        }

        let parentWrapperSelector = settings.jpgPlayerVideoMoreVideos.parentWrapperSelector;
        let viewMode = settings.jpgPlayerVideoMoreVideos.viewMode;

        $('.more-video-data', context).once('rerender').on('click keydown', function (e) {
          if (e.type === 'click' || (e.type === 'keydown' && e.key === 'Enter')) {
            let url = drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix + "jpg_player_video/ajax/rerender";
            let mediaId = $(this).data('media-id');
            let rerender = $(this).data('rerender');

            // Get from the cache.
            if (rerender && rerender.html && rerender.wrapperSelector) {
              let mediaIdSelector = "[data-media-id='" + mediaId + "']";
              let parentWrapperSelector = rerender.parentWrapperSelector;
              if (parentWrapperSelector) {
                $(mediaIdSelector).parents(parentWrapperSelector).find(wrapperSelector).html(rerender.html);
              }
              else {
                $(wrapperSelector, context).html(rerender.html);
              }
              return;
            }

            // Get from the server.
            let data = {
              'media_id': mediaId,
              'view_mode': viewMode,
              'wrapper_selector': wrapperSelector,
              'parent_wrapper_selector': parentWrapperSelector,
            };

            Drupal.ajax({
              url: url,
              submit: data
            }).
            execute().
            done(
              function (commands, statusString, ajaxObject) {
                let mediaIdSelector = "[data-media-id='" + mediaId + "']";
                let rerender = $("[data-media-id='" + mediaId + "']", context).data('rerender');
                if (rerender && rerender.html && rerender.wrapperSelector) {
                  let parentWrapperSelector = rerender.parentWrapperSelector;
                  if (parentWrapperSelector) {
                    $(mediaIdSelector).parents(parentWrapperSelector).find(wrapperSelector).html(rerender.html);
                  }
                  else {
                    $(wrapperSelector, context).html(rerender.html);
                  }
                }
              })
          }
        })
      }
    }
  }
})(Drupal, jQuery);
