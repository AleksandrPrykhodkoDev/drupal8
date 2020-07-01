(function ($, Drupal) {
  var couner = 0;
  var couner1 = 0;
  var ajaxResult;
  var locationAutocompleteSearch = function (search, type, inputSearch, errorWrap) {
    var results = [{}];
    $.ajax({
      url: '/locatiezoeker-json',
      dataType: "json",
      async: false,
      method: "POST",
      data: {search: search, type: type},
      success: function (data) {
        couner1 ++;
        $dataLenght = data.length;
        ajaxResult = data;
        if (couner1 == 1) {
          $('.path-locatiezoeker .ss-location-search-form input#edit-search, ' +
            '.path-rondleiding-aanvragen .ss-location-search-form input#edit-search, ' +
            '.path-inschrijven .ss-location-search-form input#edit-search').blur(function(event) {
              if (ajaxResult.length == 0) {
                $('.path-locatiezoeker .ss-location-search-form input#edit-search, ' +
                  '.path-rondleiding-aanvragen .ss-location-search-form input#edit-search, ' +
                  '.path-inschrijven .ss-location-search-form input#edit-search').val("");
              }
          });
        }
        errorWrap.find('div.error').remove();
        $formWrap = inputSearch.closest("form");
        $formWrap.find('div.error-top').remove();
        $submitButton = $formWrap.find('input[id*="submit"]');
        results = data;
        $valInput = inputSearch.val();
        $(".clear_input").click(function(event) {
            errorWrap.find('div.error').remove();
            $formWrap.find('div.error-top').remove();
            $('.error-in-drop').remove();
        });
        var erroreSearch = function () {
          errorWrap.find('div.error').remove();
          $formWrap.find('div.error-top').remove();
          $('.error-in-drop').remove();
          if (data.length == 0 && $valInput.search(/\d/) == 0 && type == 'locatieadres' && event.keyCode == 13 ) {
            errorWrap.append('<div class="error">' + Drupal.t('Geen resultaten gevonden. Typfoutje gemaakt misschien?') + '</div>');
          }
          else if (data.length == 0 && $valInput.search(/\d/) != 0 && type == 'locatieadres' && event.keyCode == 13 ) {
            errorWrap.append('<div class="error">' + Drupal.t('Geen resultaten gevonden. Typfoutje gemaakt misschien?') + '</div>');
          }
          else if (data.length == 0 && type == 'locatienaam' && event.keyCode == 13 ) {
            errorWrap.append('<div class="error">' + Drupal.t('Geen resultaten gevonden. Typfoutje gemaakt misschien?') + '</div>');
          }
          else if (data.length >= 0 && event.keyCode == 13) {
            $(".ui-autocomplete").prepend('<div class="error-in-drop">' + Drupal.t('Meerdere resultaten gevonden. Welke zoek je?') + '</div>');
          }
        };
        setTimeout(function(){
          $uiVis = $(".ui-autocomplete:visible");
        }, 100);
        $submitButton.click(function(event) {
          errorWrap.find('div.error').remove();
          $formWrap.find('div.error-top').remove();
          $('.error-in-drop').remove();
          inputSearch.val(inputSearch.val()).focus();
          $uiVis.show();
          if (data.length == 0 && $valInput.search(/\d/) == 0 && type == 'locatieadres' ) {
            errorWrap.append('<div class="error">' + Drupal.t('Geen resultaten gevonden. Typfoutje gemaakt misschien?') + '</div>');
            $(".ui-autocomplete").hide();
          }
          else if (data.length == 0 && $valInput.search(/\d/) != 0 && type == 'locatieadres') {
            errorWrap.append('<div class="error">' + Drupal.t('Geen resultaten gevonden. Typfoutje gemaakt misschien?') + '</div>');
            $(".ui-autocomplete").hide();
          }
          else if (data.length == 0 && type == 'locatienaam') {
            errorWrap.append('<div class="error">' + Drupal.t('Geen resultaten gevonden. Typfoutje gemaakt misschien?') + '</div>');
            $(".ui-autocomplete").hide();
          }
          else if (data.length >= 0) {
            $(".ui-autocomplete").prepend('<ol class="error-in-drop">' + Drupal.t('Meerdere resultaten gevonden. Welke zoek je?') + '</ol>');
          }
        });

        inputSearch.keypress(function(event) {
          erroreSearch();
        });

      }
    });
    return results;
  };

  Drupal.behaviors.locationSearch = {
    attach: function (context, settings) {
      couner ++;
      var makeResOrange = function () {
        if (($numOfResult == 1)) {
          $('.ui-widget-content:visible li:first-child', context).css("color", "#ff6400");
        }
      };

      // front top banner search block
      var generalSettings = {
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#front-top-banner-search-form form.ss-common-search-location-form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#front-top-banner-search-form form.ss-common-search-location-form input[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1 )) {
              $('.error-in-drop').remove();
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
              $('.error-in-drop').remove();
            }
            else if (event.keyCode == 13) {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
              }
          });
        },

        source: function (request, response) {
          var type = $('#front-top-banner-search-form form.ss-common-search-location-form input[name="searchtype"]').val();
          var errorWrap = $('#front-top-banner-search-last-location');
          var inputSearch = $('#front-top-banner-search-form form.ss-common-search-location-form input[name="search"]', context);
          response(locationAutocompleteSearch(request.term, type, inputSearch, errorWrap));
        },
        select: function(event, ui) {
          $('#front-top-banner-search-form form.ss-common-search-location-form input[name="search"]').val(ui.item.label);
          var type = $('#front-top-banner-search-form form.ss-common-search-location-form input[name="searchtype"]').val();
          if (type == 'locatienaam') {
            window.location.href = window.location.origin + ui.item.url;
          }
          else {
            $('#front-top-banner-search-form form.ss-common-search-location-form').submit();
          }
        }
      }

      $('#front-top-banner-search-form form.ss-common-search-location-form input[name="search"]').autocomplete(generalSettings).autocomplete( "widget" ).addClass( "sticky-drop" );

      //show drop down menu when change type of search
      $("#front-top-banner-search #search-switcher").click(function () {
        var $searchd = $('#front-top-banner-search-form form.ss-common-search-location-form input[name="search"]').autocomplete("search");
        $searchd.focus();
      });

      $('#front-top-banner-search-form form.ss-common-search-location-form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#front-top-banner-search-last-location div.error').remove();
        }
      });


      $('#front-top-banner-search-form form input[type="image"]').click(function (event) {
        event.preventDefault();
      });

//result of search
      if (couner == 1) {

        $('.path-locatiezoeker .ss-location-search-form input.form-checkbox, ' +
          '.path-rondleiding-aanvragen .ss-location-search-form input.form-checkbox, ' +
          '.path-inschrijven .ss-location-search-form input.form-checkbox').change(function(event) {
          var $checkLen = $('.path-locatiezoeker .ss-location-search-form input:checked, ' +
          '.path-rondleiding-aanvragen .ss-location-search-form input:checked, ' +
          '.path-inschrijven .ss-location-search-form input:checked').length;
          var valInpSearchLenght = $('.ss-location-search-form #edit-search').val().length;
          if (valInpSearchLenght > 0 && $checkLen > 0) {
            $('form.ss-location-search-form').submit();
          } else {
            event.preventDefault();
          }
          var $checkedLenght = $('#edit-services input:checked').length;
          if ($checkedLenght > 0 ) {
            $('.location-search-form .error-location').remove();
          }
        });

        $('.path-locatiezoeker .ss-location-search-form input[id*="edit-submit"], ' +
          '.path-rondleiding-aanvragen .ss-location-search-form input[id*="edit-submit"], ' +
          '.path-inschrijven .ss-location-search-form input[id*="edit-submit"]').click(function(event) {
          var $checkLe = $('.path-locatiezoeker .ss-location-search-form input:checked, ' +
          '.path-rondleiding-aanvragen .ss-location-search-form input:checked, ' +
          '.path-inschrijven .ss-location-search-form input:checked').length;
          if ($checkLe == 0) {
            $('.location-search-form .error-location').remove();
            setTimeout(function(){
              $('.ui-widget-content').hide();
            }, 1);
            if ($('.location-search-form .error').length == 0) {
              $('.location-search-form').append('<div class="error-location">' + Drupal.t('Geen resultaten gevonden. Typfoutje gemaakt misschien?') + '</div>');
            }
          } else {
            $('.location-search-form .error-location').remove();
          }
        });

        $('body.path-locatiezoeker #location-search-page form.ss-location-search-form input[name="search"]').autocomplete({
          // autoFocus: true,
           open: function( event, ui ) {
            if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
                $('.ui-autocomplete').off('menufocus hover mouseover');
            }
            $ValInp       = $('body.path-locatiezoeker #location-search-page form.ss-location-search-form input[name="search"]', context).val();
            $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
            $numOfResult  = $('.ui-widget-content:visible li', context).length;
            $nameFirstLoc = $nameFirstLoc.split(",").shift();
            makeResOrange();
            $('body.path-locatiezoeker #location-search-page form.ss-location-search-form input[id*="edit-submit"]').click(function(event) {
              var $checkL = $('.path-locatiezoeker #edit-services input:checked').length;
              if (($numOfResult == 1 && $checkL > 0 )) {
                $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
              }
              else {
                  event.preventDefault ? event.preventDefault() : (event.returnValue = false);
              }
            });
            $('input[name="search"]').keypress(function(event) {
              if (($numOfResult == 1  && event.keyCode == 13 )) {
                $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
              }
            });
          },
          source: function (request, response) {
            var type = $('#location-search-page form.ss-location-search-form input[name="searchtype"]').val();
            var errorWrap = $('#location-search-page .location-search-form');
            var inputSearch = $('#location-search-page .location-search-form input[name="search"]', context);
            response(locationAutocompleteSearch(request.term, type, inputSearch, errorWrap));
          },
          select: function(event, ui) {
            $('#location-search-page form.ss-location-search-form input[name="search"]').val(ui.item.label);
            var type = $('#location-search-page form.ss-location-search-form input[name="searchtype"]').val();
            if (type == 'locatienaam') {
              window.location.href = window.location.origin + ui.item.url;
            }
            else {
              $('#location-search-page form.ss-location-search-form').submit();
            }
          }
        });

        //show drop down menu when change type of search
        $(".location-search-form #search-switcher").click(function () {
          var $searchd = $('body.path-locatiezoeker #location-search-page form.ss-location-search-form input[name="search"]').autocomplete("search");
          $searchd.focus();
        });

        $('#location-search-page form.ss-location-search-form input[name="search"]').keyup(function() {
          var search = $(this).val();
          if (search.length == 0) {
            $('#location-search-page .location-search-form div.error').remove();
          }
        });

        $('body.path-locatiezoeker #location-search-page form.ss-location-search-form input[type="image"]').click(function (event) {
          event.preventDefault();
        });

        // tour location search page
        $('body.path-rondleiding-aanvragen #location-search-page form.ss-location-search-form input[name="search"]').autocomplete({
          // autoFocus: true,
           open: function( event, ui ) {
            if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
                $('.ui-autocomplete').off('menufocus hover mouseover');
            }
            $ValInp       = $('body.path-rondleiding-aanvragen #location-search-page form.ss-location-search-form input[name="search"]', context).val();
            $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
            $numOfResult  = $('.ui-widget-content:visible li', context).length;
            $nameFirstLoc = $nameFirstLoc.split(",").shift();
            makeResOrange();
            $('body.path-rondleiding-aanvragen #location-search-page form.ss-location-search-form input[id*="edit-submit"]').click(function(event) {
              var $checkL = $('.path-rondleiding-aanvragen #edit-services input:checked').length;
              if (($numOfResult == 1 && $checkL > 0 )) {
                $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
              }
              else {
                  event.preventDefault ? event.preventDefault() : (event.returnValue = false);
              }
            });
            $('input[name="search"]').keypress(function(event) {
              if (($numOfResult == 1 && event.keyCode == 13 )) {
                $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
              }
            });
          },
          source: function (request, response) {
            var type = $('#location-search-page form.ss-location-search-form input[name="searchtype"]').val();
            var errorWrap = $('#location-search-page .location-search-form');
            var inputSearch = $('#location-search-page .location-search-form input[name="search"]', context);
            response(locationAutocompleteSearch(request.term, type, inputSearch, errorWrap));
          },
          select: function(event, ui) {
            $('#location-search-page form.ss-location-search-form input[name="search"]').val(ui.item.label);
            var type = $('#location-search-page form.ss-location-search-form input[name="searchtype"]').val();
            if (type == 'locatienaam') {
              window.location.href = window.location.origin + ui.item.tour_url;
            }
            else {
              $('#location-search-page form.ss-location-search-form').submit();
            }
          }
        });


        $('body.path-rondleiding-aanvragen #location-search-page form.ss-location-search-form input[type="image"]').click(function (event) {
          event.preventDefault();
        });

        // registration location search page
        $('body.path-inschrijven #location-search-page form.ss-location-search-form input[name="search"]').autocomplete({
          // autoFocus: true,
           open: function( event, ui ) {
            if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
                $('.ui-autocomplete').off('menufocus hover mouseover');
            }
            $ValInp       = $('body.path-inschrijven #location-search-page form.ss-location-search-form input[name="search"]', context).val();
            $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
            $numOfResult  = $('.ui-widget-content:visible li', context).length;
            $nameFirstLoc = $nameFirstLoc.split(",").shift();
            makeResOrange();
            $('body.path-inschrijven #location-search-page form.ss-location-search-form input[id*="edit-submit"]').click(function(event) {
              var $checkL = $('.path-inschrijven #edit-services input:checked').length;
              if (($numOfResult == 1 && $checkL > 0 )) {
                $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
              }
              else {
                  event.preventDefault ? event.preventDefault() : (event.returnValue = false);
              }
            });
            $('input[name="search"]').keypress(function(event) {
              if (($numOfResult == 1 && event.keyCode == 13 )) {
                $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
              } else if (event.keyCode == 13) {
                  event.preventDefault ? event.preventDefault() : (event.returnValue = false);
                }
            });
          },
          source: function (request, response) {
            var type = $('#location-search-page form.ss-location-search-form input[name="searchtype"]').val();
            var errorWrap = $('#location-search-page .location-search-form');
            var inputSearch = $('#location-search-page .location-search-form input[name="search"]', context);
            response(locationAutocompleteSearch(request.term, type, inputSearch, errorWrap));
          },
          select: function(event, ui) {
            $('#location-search-page form.ss-location-search-form input[name="search"]').val(ui.item.label);
            var type = $('#location-search-page form.ss-location-search-form input[name="searchtype"]').val();
            if (type == 'locatienaam') {
              window.location.href = window.location.origin + ui.item.registration_url;
            }
            else {
              $('#location-search-page form.ss-location-search-form').submit();
            }
          }
        });


        $('body.path-inschrijven #location-search-page form.ss-location-search-form input[type="image"]').click(function (event) {
          event.preventDefault();
        });
      }

      // general menu search block
      $('#general-menu-search-form form.ss-common-search-location-form input[name="search"]').autocomplete({
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#general-menu-search-form form.ss-common-search-location-form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#general-menu-search-form form.ss-common-search-location-form input[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
          });
        },
        source: function (request, response) {
          var type = $('#general-menu-search-form form.ss-common-search-location-form input[name="searchtype"]').val();
          var errorWrap = $('#general-menu-search');
          var inputSearch = $('#general-menu-search input[name="search"]', context);
          response(locationAutocompleteSearch(request.term, type, inputSearch, errorWrap));
        },
        select: function(event, ui) {
          $('#general-menu-search-form form.ss-common-search-location-form input[name="search"]').val(ui.item.label);
          var type = $('#general-menu-search-form form.ss-common-search-location-form input[name="searchtype"]').val();
          if (type == 'locatienaam') {
            window.location.href = window.location.origin + ui.item.url;
          }
          else {
            $('#general-menu-search-form form.ss-common-search-location-form').submit();
          }
        }
      });

      $('#general-menu-search-form form.ss-common-search-location-form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#general-menu-search div.error').remove();
        }
      });


      $('#general-menu-search-form form.ss-common-search-location-form input[type="image"]').click(function (event) {
        event.preventDefault();
      });


      // search block in the footer
      $('#block-footersearchblock form.ss-common-search-location-form input[name="search"]').autocomplete({
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#block-footersearchblock form.ss-common-search-location-form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#block-footersearchblock form.ss-common-search-location-form input[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
          });
        },
        source: function (request, response) {
          var errorWrap = $('#block-footersearchblock');
          var inputSearch = $('#block-footersearchblock input[name="search"]', context);
          response(locationAutocompleteSearch(request.term, 'locatieadres', inputSearch, errorWrap));
        },
        select: function(event, ui) {
          $('#block-footersearchblock form.ss-common-search-location-form input[name="search"]').val(ui.item.label);
          $('#block-footersearchblock form.ss-common-search-location-form').submit();
        }
      });

      $('#block-footersearchblock form.ss-common-search-location-form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#block-footersearchblock div.error').remove();
        }
      });

      $('#block-footersearchblock form.ss-common-search-location-form input[type="image"]').click(function (event) {
        event.preventDefault();
      });

      // search block on contact page
      $('#location-contact-search-form form input[name="search"]').autocomplete({
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#location-contact-search-form form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#location-contact-search-form form input[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
          });
        },
        source: function (request, response) {
          var errorWrap = $('#location-contact-search-form');
          var inputSearch = $('#location-contact-search-form input[name="search"]', context);
          response(locationAutocompleteSearch(request.term, 'locatienaam', inputSearch, errorWrap));
        },
        select: function(event, ui) {
          $('#location-contact-search-form form input[name="search"]').val(ui.item.label);
          window.location.href = window.location.origin + ui.item.contact_url;
        }
      });

      $('#location-contact-search-form form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#location-contact-search-form div.error').remove();
        }
      });


      $('#location-contact-search-form form input[type="image"]').click(function (event) {
        event.preventDefault();
      });

      // search block on existing customers page
      $('#location-existing-customers-search-form form input[name="search"]').autocomplete({
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#location-existing-customers-search-form form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#location-existing-customers-search-form form input[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
          });
        },
        source: function (request, response) {
          // var type = $('#location-existing-customers-search-form input[name="searchtype"]').val();
          var errorWrap = $('#location-existing-customers-search-form');
          var inputSearch = $('#location-existing-customers-search-form input[name="search"]', context);
          response(locationAutocompleteSearch(request.term, 'locatienaam', inputSearch, errorWrap));
        },
        select: function(event, ui) {
          $('#location-existing-customers-search-form form input[name="search"]').val(ui.item.label);
          window.location.href = window.location.origin + ui.item.existing_customers_url + '#contact';
        }
      });

      $('#location-existing-customers-search-form form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#location-existing-customers-search-form div.error').remove();
        }
      });

      $('#location-existing-customers-search-form form input[type="image"]').click(function (event) {
        event.preventDefault();
      });

      // search block on existing customers page teaching plan section
      $('#location-existing-customers-teaching-plan-search-form form input[name="search"]').autocomplete({
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#location-existing-customers-teaching-plan-search-form form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#location-existing-customers-teaching-plan-search-form form input[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
          });
        },
        source: function (request, response) {
          // var type = $('#location-existing-customers-teaching-plan-search-form input[name="searchtype"]').val();
          var inputSearch = $('#location-existing-customers-teaching-plan-search-form input[name="search"]', context);
          var errorWrap = $('#location-existing-customers-teaching-plan-search-form');
          response(locationAutocompleteSearch(request.term, 'locatienaam', inputSearch, errorWrap));
        },
        select: function(event, ui) {
          $('#location-existing-customers-search-form form input[name="search"]').val(ui.item.label);
          window.location.href = window.location.origin + ui.item.existing_customers_url + '#teaching-plan';
        }
      });

      $('#location-existing-customers-teaching-plan-search-form form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#location-existing-customers-teaching-plan-search-form div.error').remove();
        }
      });

      $('#location-existing-customers-teaching-plan-search-form form input[type="image"]').click(function (event) {
        event.preventDefault();
      });

      // search block on existing customers page report section
      $('#location-existing-customers-report-search-form form input[name="search"]').autocomplete({
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#location-existing-customers-report-search-form form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#location-existing-customers-report-search-forminput[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
          });
        },
        source: function (request, response) {
          // var type = $('#location-existing-customers-report-search-form input[name="searchtype"]').val();
          var inputSearch = $('#location-existing-customers-report-search-form input[name="search"]', context);
          var errorWrap = $('#location-existing-customers-report-search-form');
          response(locationAutocompleteSearch(request.term, 'locatienaam', inputSearch , errorWrap));
        },
        select: function(event, ui) {
          $('#location-existing-customers-search-form form input[name="search"]').val(ui.item.label);
          window.location.href = window.location.origin + ui.item.existing_customers_url + '#protocol';
        }
      });

      $('#location-existing-customers-report-search-form form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#location-existing-customers-report-search-form div.error').remove();
        }
      });

      $('#location-existing-customers-report-search-form form input[type="image"]').click(function (event) {
        event.preventDefault();
      });

      // search block on generic page report section
      $('#generic-report-search-form form input[name="search"]').autocomplete({
        // autoFocus: true,
         open: function( event, ui ) {
          if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
              $('.ui-autocomplete').off('menufocus hover mouseover');
          }
          $ValInp       = $('#generic-report-search-form form input[name="search"]', context).val();
          $nameFirstLoc = $('.ui-widget-content:visible li:first-child', context).text();
          $numOfResult  = $('.ui-widget-content:visible li', context).length;
          $nameFirstLoc = $nameFirstLoc.split(",").shift();
          makeResOrange();
          $('#generic-report-search-form form input[id*="edit-submit"]').click(function(event) {
            if (($numOfResult == 1)) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
            else {
                event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            }
          });
          $('input[name="search"]').keypress(function(event) {
            if (($numOfResult == 1 && event.keyCode == 13 )) {
              $('.ui-widget-content:visible li:first-child, .ui-widget-content:visible ol + li', context).trigger( "click" );
            }
          });
        },
        source: function (request, response) {
          var inputSearch = $('#generic-report-search-form input[name="search"]', context);
          var errorWrap = $('#generic-report-search-form');
          response(locationAutocompleteSearch(request.term, 'locatienaam', inputSearch , errorWrap));
        },
        select: function(event, ui) {
          $('#generic-report-search-form form input[name="search"]').val(ui.item.label);
          window.location.href = window.location.origin + window.location.pathname + '?location=' + ui.item.id + '#report';
        }
      });

      $('#generic-report-search-form form input[name="search"]').keyup(function() {
        var search = $(this).val();
        if (search.length == 0) {
          $('#generic-report-search-form div.error').remove();
        }
      });

      $('#generic-report-search-form form input[type="image"]').click(function (event) {
        event.preventDefault();
      });


      var page = 1;
      $('.read-more-search').click(function (event) {
        var count = locationCenter.count;
        var referer = (settings.ss_location.search.referer) ? settings.ss_location.search.referer : 'canonical';
        var range = settings.ss_location.search.range;
        $.ajax({
          url: '/locatiezoeker-nearest',
          dataType: "html",
          async: false,
          data: {
            page: page,
            Latitude: Latitude,
            Longitude: Longitude,
            services: locationCenter.services,
            button: buttonText,
            referer: referer,
            range: range
          },
          success: function (data) {
            $('.location-search-results').append(data);
            var showed = $('.search-results-table > .row').length;
            if (showed >= count) {
              $('.read-more-search').remove();
            }
          }
        });
        page = page+1;
        event.preventDefault();
      });

      if (settings.ss_location && settings.ss_location.search) {
        var locationList = settings.ss_location.search.locations;
        var locationCenter = settings.ss_location.search.center;
        var buttonText = settings.ss_location.search.button;
        var Latitude = settings.ss_location.search.Latitude;
        var Longitude = settings.ss_location.search.Longitude;
        var useFitBounds = true;
        var center = new google.maps.LatLng(locationCenter.Latitude, locationCenter.Longitude);
        if (locationCenter.Latitude && locationCenter.Longitude) {
          useFitBounds = false;
          center = new google.maps.LatLng(locationCenter.Latitude, locationCenter.Longitude);
        }

        var mapSettings = {
          center: center,
          mapTypeControl: false,
          mapTypeId: google.maps.MapTypeId.ROADMAP,
          zoom: 17,
          fitBounds: false,
          zoomControlOptions: {style: google.maps.ZoomControlStyle.SMALL},
        };

        var GMap = new google.maps.Map(document.getElementById('locations-results-map'), mapSettings);

        var GInfoBox = new InfoBox({
          content: '',
          boxClass: 'map-infobox',
          closeBoxMargin: '2px 2px 2px 2px',
          closeBoxURL: 'http://www.google.com/intl/en_us/mapfiles/close.gif',
          enableEventPropagation: true,
          pixelOffset: new google.maps.Size(35, -60),
          infoBoxClearance: new google.maps.Size(20, 50)
        });

        var GOMS = new OverlappingMarkerSpiderfier(GMap, {
          markersWontMove: true,
          markersWontHide: true,
          keepSpiderfied: true,
          nearbyDistance: 20,
          legWeight: 8
        });

        GOMS.addListener('click', function (Marker, Event) {
          GInfoBox.setContent(Marker.Info);
          GInfoBox.open(GMap, Marker);
        });

        var GMapBounds = new google.maps.LatLngBounds();

        for (var key in locationList) {
          var GMapMarker = new google.maps.Marker({
            map: GMap,
            position: new google.maps.LatLng(locationList[key].lat, locationList[key].lng),
            icon: locationList[key].icon,
            Info: locationList[key].info
          });

          if (locationList[key].lat == locationCenter.Latitude && locationList[key].lng == locationCenter.Longitude) {
            GInfoBox.setContent(GMapMarker.Info);
            GInfoBox.open(GMap, GMapMarker);
          }

          GOMS.addMarker(GMapMarker);

          GMapBounds.extend(new google.maps.LatLng(locationList[key].lat, locationList[key].lng));
        }

        if (useFitBounds) {
          GMap.fitBounds(GMapBounds);
        }

        google.maps.event.addDomListener(window, 'resize', function () {
          var Center = GMap.getCenter();
          google.maps.event.trigger(GMap, 'resize');
          GMap.setCenter(Center);

          if (useFitBounds) {
            GMap.fitBounds(GMapBounds);
          }
        });
      }
      // Map collapse btn
      $('#location-search-page .map-colapse-btn').click(function(e) {
        e.preventDefault();
        var $this = $(this, context);
        $this.closest('.nearest-result-map').toggleClass('full-width');
        setTimeout(function(){
          $this.closest('.location-search-results-inner').next('.search-results-table').find('.row:first-child').toggle();
          var Center = GMap.getCenter();
          google.maps.event.trigger(GMap, 'resize');
          GMap.setCenter(Center);
        }, 201);
        return false;
      });
    }
  };
})(jQuery, Drupal);
