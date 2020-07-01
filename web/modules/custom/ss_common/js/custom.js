(function ($, Drupal) {

  function genericPageTypeSection(selector) {
    var type = $(selector).val();
    $parents = $(selector).parents('tr');

    $parents.find('.field--name-field-intro-section-type').hide();
    $parents.find('.field--name-field-text-button-section').hide();
    $parents.find('.field--name-field-intro-section').hide();
    $parents.find('.field--name-field-layout').hide();
    $parents.find('.field--name-field-review-section').hide();
    $parents.find('.field--name-field-services-section').hide();
    $parents.find('.field--name-field-search-box').hide();
    $parents.find('.field--name-field-expert-section').hide();
    $parents.find('.field--name-field-full-width-text-section').hide();

    $(".field--name-field-type-section select option[value='_none']").remove();

    if (type == 'intro_section') {
      $parents.find('.field--name-field-intro-section-type').show();
      $parents.find('.field--name-field-intro-section').show();
    }

    if (type == 'text_button_section') {
      $parents.find('.field--name-field-text-button-section').show();
      $parents.find('.field--name-field-intro-section').hide();
    }

    if (type == 'layout_options') {
      $parents.find('.field--name-field-layout').show();
      $parents.find('.field--name-field-text-button-section').hide();
      $parents.find('.field--name-field-intro-section').hide();
    }

    if (type == 'review_section') {
      $parents.find('.field--name-field-review-section').show();
      $parents.find('.field--name-field-text-button-section').hide();
      $parents.find('.field--name-field-intro-section').hide();
    }

    if (type == 'services_section') {
      $parents.find('.field--name-field-services-section').show();
    }

    if (type == 'search_box') {
      $parents.find('.field--name-field-search-box').show();
    }

    if (type == 'expert_section') {
      $parents.find('.field--name-field-expert-section').show();
    }

    if (type == 'text_section') {
      $parents.find('.field--name-field-full-width-text-section').show();
    }
  }

  function genericPageIntroSectionToggle(selector) {
    var type = $(selector).val();
    $parents = $(selector).parents('.field--name-field-intro-section div');

    $parents.find('.field--name-field-title').hide();
    $parents.find('.field--name-field-link').hide();
    $parents.find('.field--name-field-image').hide();
    $parents.find('.field--name-field-intro-text-section').hide();

    $(".field--name-field-intro-section-type select option[value='_none']").remove();

    if (type == 'simple') {
      $parents.find('.field--name-field-title').show();
      $parents.find('.field--name-field-link').show();
      $parents.find('.field--name-field-intro-text-section').show();
    }

    if (type == 'background') {
      $parents.find('.field--name-field-title').show();
      $parents.find('.field--name-field-link').show();
      $parents.find('.field--name-field-image').show();
      $parents.find('.field--name-field-intro-text-section').show();
    }
  }

  function genericPageLayoutToggle(selector) {
    var type = $(selector).val();
    $parents = $(selector).parents('.field--name-field-layout div.fieldset-wrapper');

    $parents.find('.field--name-field-intro-text').hide();
    $parents.find('.field--name-field-number-intro-text').hide();
    $parents.find('.field--name-field-title-intro-text').hide();
    $parents.find('.field--name-field-background-color').hide();
    $parents.find('.field--name-field-link-intro-text').hide();
    $parents.find('.field--name-field-pdf-intro-text').hide();


    $(".field--name-field-background-color select option[value='_none']").remove();
    $(".field--name-field-layout-type select option[value='_none']").remove();

    if (type == 'left') {
      $parents.find('.field--name-field-intro-text').show();
      $parents.find('.field--name-field-number-intro-text').show();
      $parents.find('.field--name-field-title-intro-text').show();
      $parents.find('.field--name-field-background-color').show();
      $parents.find('.field--name-field-link-intro-text').show();
      $parents.find('.field--name-field-pdf-intro-text').show();
    }

    if (type == 'right') {
      $parents.find('.field--name-field-intro-text').show();
      $parents.find('.field--name-field-number-intro-text').show();
      $parents.find('.field--name-field-title-intro-text').show();
      $parents.find('.field--name-field-background-color').show();
      $parents.find('.field--name-field-link-intro-text').show();
      $parents.find('.field--name-field-pdf-intro-text').show();
    }
  }

  function genericPageLayoutImageToggle(selector) {
    var type = $(selector).val();
    $parents = $(selector).parents('.field--name-field-layout div.fieldset-wrapper');

    $parents.find('.field--name-field-video-layout').hide();
    $parents.find('.field--name-field-image-layout').hide();
    $parents.find('.field--name-field-slider-layout').hide();
    $parents.find('.field--name-field-second-text').hide();
    $parents.find('.field--name-field-map-layout').hide();
    $parents.find('.field--name-field-number-second-text').hide();
    $parents.find('.field--name-field-title-second-text').hide();
    $parents.find('.field--name-field-link-second-text').hide();
    $parents.find('.field--name-field-pdf-second-text').hide();

    if (type == 'video') {
      $parents.find('.field--name-field-video-layout').show();
    }

    if (type == 'image') {
      $parents.find('.field--name-field-image-layout').show();
    }

    if (type == 'slider') {
      $parents.find('.field--name-field-slider-layout').show();
    }

    if (type == 'text') {
      $parents.find('.field--name-field-second-text').show();
      $parents.find('.field--name-field-number-second-text').show();
      $parents.find('.field--name-field-title-second-text').show();
      $parents.find('.field--name-field-link-second-text').show();
      $parents.find('.field--name-field-pdf-second-text').show();
    }

    if (type == 'map') {
      $parents.find('.field--name-field-map-layout').show();
    }
  }

  Drupal.behaviors.myNewBehavior = {
    attach: function (context, settings) {
      $(".field--name-field-type-section select").is(function () {
        genericPageTypeSection(this);
      });

      $(".field--name-field-type-section select").click(function () {
        genericPageTypeSection(this);
      });

      $(".field--name-field-intro-section-type select").is(function () {
        genericPageIntroSectionToggle(this);
      });

      $(".field--name-field-intro-section-type select").click(function () {
        genericPageIntroSectionToggle(this);
      });

      $(".field--name-field-layout .field--name-field-layout-type select").is(function () {
        genericPageLayoutToggle(this);
      });

      $(".field--name-field-layout .field--name-field-layout-type select").click(function () {
        genericPageLayoutToggle(this);
      });

      $(".field--name-field-layout .field--name-field-layout-image-type select").is(function () {
        genericPageLayoutImageToggle(this);
      });

      $(".field--name-field-layout .field--name-field-layout-image-type select").click(function () {
        genericPageLayoutImageToggle(this);
      });
    }
  };
})(jQuery, Drupal);
