<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\FooterLogoBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a footer logo block.
 *
 * @Block(
 *   id = "footer_logo_block",
 *   admin_label = @Translation("Footer logo block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class FooterLogoBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::state();
    $theme_path = drupal_get_path('theme', 'smallsteps');

    return array(
      '#type' => 'markup',
      '#markup' => '<img src="/' . $theme_path . '/images/logo.png" class="logo-image"/>
        <div>' . $config->get('footer_settings.footer_title') . '</div>',
    );
  }
}
