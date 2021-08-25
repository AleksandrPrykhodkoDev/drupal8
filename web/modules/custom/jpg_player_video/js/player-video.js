/**
 * @file
 * Behaviors for the "Player video" paragraph.
 */

(function (Drupal) {
  Drupal.behaviors.jpgPlayerVideoMoreVideos = {
    attach(context) {
      function originalVideoSrcProcess(originalVideo, moreVideo) {
        if (
          originalVideo.getAttribute('src') !==
          moreVideo.dataset.fieldMediaVideoFileUrl
        ) {
          originalVideo.setAttribute(
            'src',
            moreVideo.dataset.fieldMediaVideoFileUrl,
          );
          originalVideo.play();
        }
      }

      Array.prototype.forEach.call(
        context.querySelectorAll(
          '.paragraph--type--player-video.paragraph--view-mode--default:not(.js-player-video-more-videos)',
        ),
        function (el) {
          el.classList.add('js-player-video-more-videos');
          var originalVideo = el.querySelector(
            '.field--name-field-media-video-file video',
          );
          if (originalVideo) {
            Array.prototype.forEach.call(
              el.querySelectorAll('.more-video-data'),
              function (moreVideo) {
                moreVideo.setAttribute('role', 'button');
                moreVideo.setAttribute(
                  'aria-label',
                  Drupal.t('Show this video'),
                );
                moreVideo.setAttribute('tabindex', '0');
                moreVideo.addEventListener('click', function () {
                  originalVideoSrcProcess(originalVideo, moreVideo);
                });

                moreVideo.addEventListener('keydown', function (e) {
                  if (e.key === 'Enter') {
                    originalVideoSrcProcess(originalVideo, moreVideo);
                  }
                });
              },
            );
          }
        },
      );
    },
  };
})(Drupal);
