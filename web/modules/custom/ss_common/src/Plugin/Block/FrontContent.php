<?php

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\file\Entity\File;

/**
 * Provides a 'Front Content' block.
 *
 * @Block(
 *   id = "ss_common_front_content",
 *   admin_label = @Translation("Front Content")
 * )
 */
class FrontContent extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::state();
    $build = [];

    for ($i=1; $i<=3; $i++) {
      $build['articles'][$i] = [
        'image' => NULL,
        'title' => $config->get("front_page.content.title_$i"),
        'text' => $config->get("front_page.content.text_$i"),
        'link' => $config->get("front_page.content.link_$i"),
        'button' => $config->get("front_page.content.button_$i"),
        'class' => $i%2 == 0 ? 'right' : 'left',
	      'number' => $config->get("front_page.content.number_$i"),
      ];

      if ($config->get("front_page.content.image_$i")) {
        $file = File::load($config->get("front_page.content.image_$i")[0]);
        $url = file_create_url($file->getFileUri());
        $build['articles'][$i]['image'] = $url;
      }
    }

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
