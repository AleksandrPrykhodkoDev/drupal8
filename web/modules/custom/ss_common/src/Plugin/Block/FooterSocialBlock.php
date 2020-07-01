<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\FooterSocialBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\ss_common\Form\SocialSettings;
use Drupal\file\Entity\File;

/**
 * Provides a footer social block.
 *
 * @Block(
 *   id = "footer_social_block",
 *   admin_label = @Translation("Footer social block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class FooterSocialBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $config = \Drupal::state();
    foreach (SocialSettings::getSocial() as $key => $name) {
      if ($config->get('social.' . $key . '.link')) {
        $build[$key]['name'] = $name;
        $build[$key]['link'] = $config->get('social.' . $key . '.link');
      }
      if ($config->get('social.' . $key . '.image')) {
        $file = File::load($config->get('social.' . $key . '.image')[0]);
        $url = file_create_url($file->getFileUri());
        $build[$key]['image'] = $url;
      }
    }
    return $build;
  }
}
