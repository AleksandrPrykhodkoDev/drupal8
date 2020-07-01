<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\HeaderButtonsBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a header buttons block.
 *
 * @Block(
 *   id = "header_buttons_block",
 *   admin_label = @Translation("Header buttons block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class HeaderButtonsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $current_path = \Drupal::service('path.current')->getPath();
    $url_object = \Drupal::service('path.validator')->getUrlIfValid($current_path);

    if ($url_object) {
      $route_name = $url_object->getRouteName();

      $location_forms = [
        'entity.ss_location.tour',
        'entity.ss_location.tour.thank',
        'entity.ss_location.registration',
        'entity.ss_location.registration.thank',
      ];

      if (in_array($route_name, $location_forms)) {
        return $build;
      }

      $calculator_form = [
        'ss_location.calculator',
      ];

      if (in_array($route_name, $calculator_form)) {
        $build['terug'] = Link::fromTextAndUrl(t('Terug'), Url::fromRoute(('<front>')));
        if (isset($_SESSION['location_costcalculation']['permalink'])) {
          $permalink = $_SESSION['location_costcalculation']['permalink'];
          $build['linkcalculator'] = Link::fromTextAndUrl(t('rondleiding aanvragen'), Url::fromRoute('entity.ss_location.canonical', ['ss_location' => $permalink]));
        }
        else {
          $build['linkcalculator'] = Link::fromTextAndUrl(t('rondleiding aanvragen'), Url::fromRoute(('<front>')));
        }
        return $build;
      }
    }
    
    $theme_path = drupal_get_path('theme', 'smallsteps');
    $form = \Drupal::formBuilder()->getForm('Drupal\ss_common\Form\SearchLocation', t('Waar ben je naar op zoek?'), 'textsearch');
    $build['buttons'] = [
      'search' => $form,
      'menu' => ['#markup' => '/' . $theme_path . '/images/menu.png'],
      'close' => ['#markup' => '/' . $theme_path . '/images/close-general-menu.png'],
    ];
    
    return $build;
  }
}
