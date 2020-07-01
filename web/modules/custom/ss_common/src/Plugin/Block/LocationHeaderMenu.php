<?php
/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\LocationHeaderMenu.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a location header block with links.
 *
 * @Block(
 *   id = "location_header_menu_block",
 *   admin_label = @Translation("Location header menu block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class LocationHeaderMenu extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $params = \Drupal::routeMatch()->getRawParameters();
    $location = $params->get('ss_location');

    $current_path = \Drupal::service('path.current')->getPath();
    $url_object = \Drupal::service('path.validator')->getUrlIfValid($current_path);

    $build = [];

    if ($url_object) {
      $route_name = $url_object->getRouteName();

      $location_forms = [
        'entity.ss_location.tour',
        'entity.ss_location.tour.thank',
        'entity.ss_location.registration',
        'entity.ss_location.registration.thank'
      ];

      $location_registration_form = [
        'entity.ss_location.registration',
        'entity.ss_location.registration.thank'
      ];

      $location_tour_form = [
        'entity.ss_location.tour',
        'entity.ss_location.tour.thank',
      ];

      $search_forms = [
        'entity.ss_location.search'
      ];

      if ($location) {
        if (in_array($route_name, $location_tour_form)) {
          $location_storage = \Drupal::entityTypeManager()->getStorage('ss_location');
          $locationname = $location_storage->load($location);

          $build['telephone']['#markup'] = t('0800 770 7707');
          $build['links'] = [];
          $build['locationname'] = $locationname->getName();
        }

        if (in_array($route_name, $location_forms)) {
          $build['class'][] = 'location-form-block';

          if (in_array($route_name, $location_registration_form)) {
            $build['links'] = [
              Link::fromTextAndUrl(t('Terug'), Url::fromRoute('entity.ss_location.canonical', ['ss_location' => $location], ['attributes' => ['class' => ['tour-button button']]]))
            ];
          }
//          if (isset($_SERVER['HTTP_REFERER'])) {
//            $build['links'] = [
//              Link::fromTextAndUrl('x', Url::fromUri($_SERVER['HTTP_REFERER'], ['attributes' => ['class' => ['close-button']]]))
//            ];
//          }
//        }
//        else {
//          $build['links'] = [
//             '<span class = "header_kom">'.t('Kom gezellig kijken').'</span>',
//            Link::fromTextAndUrl(t('rondleiding aanvragen'), Url::fromRoute('entity.ss_location.tour', ['ss_location' => $location], ['attributes' => ['class' => ['read-more-link', 'read-more-cl']]]))
//          ];
//
//          $build['phone']['#markup'] = t('Of bel @phone', ['@phone' => '08007707707']);
        }
      }
      else {
        if (in_array($route_name, $search_forms)) {
          $build['class'][] = 'location-form-block';
          $build['links'] = [
            Link::fromTextAndUrl('x', Url::fromRoute('<front>', [], ['attributes' => ['class' => ['close-button']]]))
          ];
        }
      }
    }

    return $build;
  }
}
