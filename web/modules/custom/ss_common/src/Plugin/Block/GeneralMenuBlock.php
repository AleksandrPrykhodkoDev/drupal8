<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\GeneralMenuBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\ss_common\Form\SocialSettings;
use Drupal\file\Entity\File;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a general menu block.
 *
 * @Block(
 *   id = "general_menu_block",
 *   admin_label = @Translation("General menu block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class GeneralMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $theme_path = drupal_get_path('theme', 'smallsteps');
    $config = \Drupal::state();
    $location_storage = \Drupal::entityTypeManager()->getStorage('ss_location');
    $form = \Drupal::formBuilder()->getForm('Drupal\ss_common\Form\SearchLocation');

    $build['logo'] = [
      'image' => ['#markup' => '/' . $theme_path . '/logo.png'],
      'title' => [
        //'#markup' => 'kinderopvang'
      ],
    ];

    $build['close'] = [
      'image' => ['#markup' => '/' . $theme_path . '/images/close.png'],
    ];

    $menu_tree = \Drupal::menuTree();
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    $menu_names = ['ons-aanbod', 'waarom-smallsteps', 'over-smallsteps', 'contact'];

    foreach ($menu_names as $menu_name) {
      $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
      $tree = $menu_tree->load($menu_name, $parameters);

      $tree = $menu_tree->transform($tree, $manipulators);
      $menu = $menu_tree->build($tree);

      $menu_title = \Drupal::entityTypeManager()->getStorage('menu')->load($menu_name)->label();

      $build[str_replace('-', '_', $menu_name)] = [
        'title' => ['#markup' => $menu_title],
        'links' => ['#markup' => \Drupal::service('renderer')->render($menu)],
      ];
    }

    $last_location_id = ss_location_get_last_location();
    $last_location_link = NULL;
    if ($last_location_id) {
      $location = $location_storage->load($last_location_id);
      $location_city = $location->getCity();
      $location_city_text = ' (' . $location_city . ')';
      $build['namelocation'] = $location->getName();

      if ($location) {
        $last_location_link = Link::fromTextAndUrl(t('Smallsteps ') . $location->getName() . $location_city_text, Url::fromRoute('entity.ss_location.canonical', ['ss_location' => $location->getPath()]));
      }
    }

    $build['search'] = [
      'title' => t('Locaties'),
      'label' => ['#markup' => 'Vind een Smallsteps bij jou in de buurt'],
      'form' => ['#markup' => render($form)],
      'last_location_label' => t('Laatst bezocht:'),
      'last_location_link' => $last_location_link,
      'switcher_title' => t('zoek op locatienaam'),
      'switcher_action' => 'locatienaam'
    ];

    foreach (SocialSettings::getSocial() as $key => $name) {
      if ($config->get('social.' . $key . '.link')) {
        $build['social'][$key]['name'] = $name;
        $build['social'][$key]['link'] = $config->get('social.' . $key . '.link');
      }
      if ($config->get('social.' . $key . '.image')) {
        $file = File::load($config->get('social.' . $key . '.image')[0]);
        $url = file_create_url($file->getFileUri());
        $build['social'][$key]['image'] = $url;
      }
    }

    $params = \Drupal::routeMatch()->getRawParameters();
    $location_id = $params->get('ss_location');

    $page = 'default';
    if ($location_id) {
      $page = 'location';
      $location = $location_storage->load($location_id);

      $build['location']['name'] = $location->getName();

      $build['location']['services'] = [];
      if ($location->getServiceKDV() == 1) {
        $build['location']['services'][] = Link::fromTextAndUrl($location->getMainServiceKDVTitle(), Url::fromRoute('entity.ss_location.service.kdv', ['ss_location' => $location->getPath()]));
      }

      if ($location->getServicePSZ() == 1) {
        $build['location']['services'][] = Link::fromTextAndUrl($location->getMainServicePSZTitle(), Url::fromRoute('entity.ss_location.service.psz', ['ss_location' => $location->getPath()]));
      }

      if ($location->getServiceBSO() == 1) {
        $build['location']['services'][] = Link::fromTextAndUrl($location->getMainServiceBSOTitle(), Url::fromRoute('entity.ss_location.service.bso', ['ss_location' => $location->getPath()]));
      }

      $build['location']['links'] = [
        Link::fromTextAndUrl(t('Onze locatie'), Url::fromRoute('entity.ss_location.location', ['ss_location' => $location->getPath()])),
        Link::fromTextAndUrl(t('Rondleiding aanvragen'), Url::fromRoute('entity.ss_location.tour', ['ss_location' => $location->getPath()])),
        Link::fromTextAndUrl(t('Meteen inschrijven'), Url::fromRoute('entity.ss_location.registration', ['ss_location' => $location->getPath()])),
        Link::fromTextAndUrl(t('Contact'), Url::fromRoute('entity.ss_location.canonical', ['ss_location' => $location->getPath()], ['fragment' => 'contacts']))
      ];

      if ($location->getSocialFacebook()) {
        $build['location']['facebook'] = Link::fromTextAndUrl(t('Facebook'), Url::fromUri($location->getSocialFacebook(), ['attributes' => ['target' => '_blank']]));
      }
    }

    $build['page'] = $page;

    return $build;
  }
}
