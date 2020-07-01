(function ($, Drupal) {
  Drupal.behaviors.tourForm = {
    attach: function (context, settings) {
      var servicesCheckboxes = '#services-wrapper input[type="checkbox"]';
      var servicesSubmit = '#services-wrapper input[type="submit"]';
      var addressDayContainer = '#address-wrapper';

      //valid metod
      $.validator.addMethod("Locationservices", function(value, element) {
        return $('#edit-locationservices input[type=checkbox]:checked', context).length > 0;
      }, Drupal.t("Kies een dienst."));

      //validation
      $('form.ss-location-tour-form', context).validate({
        ignore: [],
        rules: {
          'LocationServices[KDV]': { Locationservices: true },
          NameTitle: {
            required: true
          },
          ContactPhone: {
            required: false,
            minlength: 10
          },
        },
        messages: {
          NameTitle: {required: Drupal.t('Aanhef is nog niet aangevinkt.')},
          NameFirst: {required: Drupal.t('Je voornaam is nog niet ingevuld.')},
          NameLast: {required: Drupal.t('Je achternaam is nog niet ingevuld.')},
          ContactEmail: {
            required: Drupal.t('Je e-mailadres is nog niet (goed) ingevuld.'),
            email: Drupal.t('Je e-mailadres is nog niet (goed) ingevuld.'),
        },
          ContactPhone: {
            minlength: Drupal.t("Nummer moet uit 10 cijfers bestaan."),
          }
        },
        errorPlacement: function(error, element) {
          if (element.attr("name") == "LocationServices[KDV]" ) {
            error.insertAfter($("#edit-locationservices"));
          }
          else
          {
            error.insertAfter(element);
          }
        }
      });

      //check for label.error if = 0 show load amm
      $('#address-wrapper #edit-submit', context).click(function(event) {
          setTimeout(function(){
            var $errorLabelTour = $(".ss-location-tour-form").find("label.error:visible");
            if ( $errorLabelTour.length ) {
               event.preventDefault();
              return false;
            }
            else {
              $('.apend-load').remove();
              $(".ss-location-tour-form #edit-submit", context).after("<div class='apend-load'> "+
                "<span class='apend-text'><img src='/themes/smallsteps/images/load.gif'> Een ogenbik geduld...</span> </div>");
            }
        }, 100);
      });
      //end check for label.error

      $('#edit-locationservices input[type="checkbox"]').change(function () {
        $("#edit-locationservices + label.error", context).hide();
      });

      //scroll to error
      $('#address-wrapper #edit-submit').click(function(e) {
        if ($('#address-wrapper input[name="NameTitle"]:checked').length == 0) {
          var scrollOffset = $('#address-wrapper').offset().top - 150;
          $(window).scrollTop(scrollOffset);
        }
        else if ($('#edit-locationservices input:checked').length == 0) {
          var scrollOffset = $('#edit-locationservices').offset().top - 150;
          $(window).scrollTop(scrollOffset);
        }
      });

      $('#address-wrapper input[name="NameTitle"]').click(function(event) {
        $('#address-wrapper input[name="NameTitle"]').closest('.form-radios').find('label.error').remove();
      });

      //not click logo
      $('.site-logo').css({
        cursor: 'default'
      });
      $('.site-logo').click(function(event) {
         event.preventDefault();
      });
      // // choose services, checkboxes are blocked if first step is completed
      // $(servicesCheckboxes).click(function (event) {
      //   if ($(this).hasClass('disabled')) {
      //     event.preventDefault();
      //     return;
      //   }

      //   var servicesChecked = 0;
      //   $(servicesCheckboxes).each(function () {
      //     if (this.checked != false) {
      //       servicesChecked++;
      //     }
      //   });
      //   if (servicesChecked > 0) {
      //     $(servicesSubmit).removeClass('hidden');
      //   }
      //   else {
      //     $(servicesSubmit).addClass('hidden');
      //   }
      // });

      // // complete first step, show preferences section
      // $(servicesSubmit).click(function (event) {
      //   $(servicesCheckboxes).addClass('disabled');
      //   $(preferencesDayContainer).removeClass('hidden');
      //   event.preventDefault();
      //   $(this).remove();
      // });
    }
  };

})(jQuery, Drupal);
