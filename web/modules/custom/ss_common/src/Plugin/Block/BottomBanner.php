<?php

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\file\Entity\File;

/**
 * Provides a 'Bottom Banner' block.
 *
 * @Block(
 *   id = "ss_common_bottom_banner",
 *   admin_label = @Translation("Bottom Banner"),
 *   category = @Translation("Smallsteps")
 * )
 */
class BottomBanner extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::state();
    if (!$config->get('front_page.bottom.banner_enable') || !$config->get('front_page.bottom.banner_image')) {
      return;
    }

    $file = File::load($config->get('front_page.bottom.banner_image')[0]);
    $url = file_create_url($file->getFileUri());
    $build = [
      'image' => [
        '#markup' => $url,
      ],
    ];
    return $build;
  }
}
