/**
 * @file
 * JPG Modals. Popin.
 */

(function ($, Drupal, drupalSettings, cookies) {
  "use strict";
  Drupal.behaviors.jpgModalsPopin = {
    attach: function (context, settings) {
      if (!drupalSettings.jpg_modals || !drupalSettings.jpg_modals.popin) {
        return;
      }

      if (!drupalSettings.jpg_modals.popin.enabled) {
        return;
      }

      $("#popin-wrapper", context)
        .once("jpg-modal-popin")
        .each(function () {
          let $this = $(this);
          $this.removeClass("hidden");

          if (drupalSettings.jpg_modals.popin.is_logged) {
            const loggedCount = cookies.get('jpg_modal_logged_count');
            if (loggedCount == null) {
              cookies.set('jpg_modal_logged_count', 1);
            }
            else {
              $this.addClass("hidden");
              return;
            }
          }
          else {
            let anonCount = cookies.get('jpg_modal_anon_count');
            if (anonCount == null) {
              cookies.set('jpg_modal_anon_count', 1);
            }
            else if (anonCount >= drupalSettings.jpg_modals.popin.times_to_show){
              $this.addClass("hidden");
              return;
            }
            else {
              cookies.set('jpg_modal_anon_count', ++anonCount);
            }
          }

          const popinContent = $(".popin-content", $this);

          let popin = Drupal.dialog($this, {
            title: null,
            dialogClass: "popin-modal",
            width: popinContent.outerWidth(),
            height: "auto",
            autoResize: true,
            close: function (event) {
              $(event.target).remove();
            },
          });

          $(".popin-content__close", popinContent).click(() => {
            $this.dialog("close");
          });

          popin.showModal();
        });
    },
  };
})(jQuery, Drupal, drupalSettings, window.Cookies);
