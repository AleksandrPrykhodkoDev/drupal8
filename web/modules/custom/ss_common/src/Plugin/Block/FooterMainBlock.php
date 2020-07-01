<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\FooterMainBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a footer main block.
 *
 * @Block(
 *   id = "footer_main_block",
 *   admin_label = @Translation("Footer main block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class FooterMainBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::state();
    $location_storage = \Drupal::entityTypeManager()->getStorage('ss_location');

    $build = [];

    $build['class'][] = 'footer-main-block';

    $menu_tree = \Drupal::menuTree();
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    $menu_name = 'smallsteps-footer-menu';
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = $menu_tree->load($menu_name, $parameters);

    $tree = $menu_tree->transform($tree, $manipulators);
    $divided_tree = array_chunk($tree, ceil(count($tree) / 2), TRUE);

    $left_menu = $menu_tree->build($divided_tree[0]);
    $right_menu = isset($divided_tree[1]) ? $menu_tree->build($divided_tree[1]) : NULL;

    $build['left_menu'] = \Drupal::service('renderer')->render($left_menu);
    $build['right_menu'] = $right_menu ? \Drupal::service('renderer')->render($right_menu) : $right_menu;

    $params = \Drupal::routeMatch()->getRawParameters();
    $location_id = $params->get('ss_location');

    if ($location_id) {
      $build['class'][] = 'location-footer-main-block';
      $location = $location_storage->load($location_id);

      $tour_link = Link::fromTextAndUrl($config->get('footer_settings.location_footer_link'), Url::fromRoute('entity.ss_location.tour', ['ss_location' => $location_id], ['attributes' => ['class' => ['read-more-link', 'read-more-cl']]]));

      $build['links'] = [
        $config->get('footer_settings.location_footer_title'),
        $tour_link
      ];

      $build['phone'] = $config->get('footer_settings.location_footer_phone');

      $full_address = [];
      $full_address[] = t('Smallsteps') . ' ' . $location->getName();
      $full_address[] = $location->getStreetAddress();
      $full_address[] = $location->getPostCode() . ' ' . $location->getCity();
      $build['location'] = implode('<br />', $full_address);
      $build['location_phone'] = $location->getTelephone();
      if ($location->getSocialFacebook()) {
        $build['location_facebook'] = Link::fromTextAndUrl(t('Facebook'), Url::fromUri($location->getSocialFacebook(), ['attributes' => ['target' => '_blank']]));
      }

      $location_tree = $menu_tree->load('location-generic-footer', $parameters);
      $location_tree = $menu_tree->transform($location_tree, $manipulators);
      $location_menu = $menu_tree->build($location_tree);

      $build['left_menu'] = [
        '#theme' => 'item_list',
        '#items' => [],
        '#attributes' => [
          'class' => ['menu']
        ]
      ];

      $build['left_menu']['#items'][] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.ss_location.canonical', ['ss_location' => $location->getPath()]),
        '#title' => t('Onze locatie'),
        '#wrapper_attributes' => ['class' => ['menu-item']]
       ];

      if ($location->getServiceKDV() == 1) {
        $build['left_menu']['#items'][] = [
          '#type' => 'link',
          '#url' => Url::fromRoute('entity.ss_location.service.kdv', ['ss_location' => $location->getPath()]),
          '#title' => $location->getMainServiceKDVTitle(),
          '#wrapper_attributes' => ['class' => ['menu-item']]
        ];
      }

      if ($location->getServicePSZ() == 1) {
        $build['left_menu']['#items'][] = [
          '#type' => 'link',
          '#url' => Url::fromRoute('entity.ss_location.service.psz', ['ss_location' => $location->getPath()]),
          '#title' => $location->getMainServicePSZTitle(),
          '#wrapper_attributes' => ['class' => ['menu-item']]
        ];
      }

      if ($location->getServiceBSO() == 1) {
        $build['left_menu']['#items'][] = [
          '#type' => 'link',
          '#url' => Url::fromRoute('entity.ss_location.service.bso', ['ss_location' => $location->getPath()]),
          '#title' => $location->getMainServiceBSOTitle(),
          '#wrapper_attributes' => ['class' => ['menu-item']]
        ];
      }

      $build['right_menu'] = \Drupal::service('renderer')->render($location_menu);
    }

    return $build;
  }

}
