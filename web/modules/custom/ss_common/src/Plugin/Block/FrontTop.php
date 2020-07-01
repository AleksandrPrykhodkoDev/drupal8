<?php

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Provides a 'Front Top' block.
 *
 * @Block(
 *   id = "ss_common_front_top",
 *   admin_label = @Translation("Front Top")
 * )
 */
class FrontTop extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\ss_common\Form\SearchLocation');

    $location_storage = \Drupal::entityTypeManager()->getStorage('ss_location');
    $last_location_id = ss_location_get_last_location();
    $last_location_link = NULL;
    if ($last_location_id) {
      $location = $location_storage->load($last_location_id);
      $location_city = $location->getCity();
      $location_city_text = ' (' . $location_city . ')';

      if ($location) {
        $last_location_link = Link::fromTextAndUrl($location->getName(), Url::fromRoute('entity.ss_location.canonical', ['ss_location' => $location->getPath()]));
        $last_location_link_with_city = Link::fromTextAndUrl(t('Smallsteps ') . $location->getName() . $location_city_text, Url::fromRoute('entity.ss_location.canonical', ['ss_location' => $location->getPath()]));
      }
    }

    $switcher_title = t('zoek op locatienaam');
    $switcher_action = 'locatienaam';

    $config = \Drupal::state();

    $top_image = NULL;
    if ($config->get('front_page.top.top_image')) {
      $file = File::load($config->get('front_page.top.top_image')[0]);
      $top_image = file_create_url($file->getFileUri());
    }

    $build = [
      'title' => [
        '#markup' => $config->get('front_page.top.title'),
      ],
      'text' => [
        '#markup' => $config->get('front_page.top.text_bellow_image'),
      ],
      'image' => $top_image,
      'search' => [
        'title' => [
          '#markup' => $config->get('front_page.top.sub_title'),
        ],
        'form' => [
          '#markup' => render($form),
        ],
        'last_location_link_with_city' => $last_location_link_with_city,
        'last_location_label' => t('Laatst bezocht:'),
        'last_location_link' => $last_location_link,
        'switcher_title' => $switcher_title,
        'switcher_action' => $switcher_action,
        'last_location' => [
          '#markup' => '<span><b>Laatst bezocht:</b> Smallsteps Apollo</span><span class="divider"> | </span><span><b>></b> Zoek op locatienaam</span>',
        ]
      ],
      'logo' => [
        '#markup' => '/' . drupal_get_path('theme','smallsteps') . '/logo.png',
      ],
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return parent::getCacheMaxAge();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return parent::getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return parent::getCacheContexts();
  }

}
