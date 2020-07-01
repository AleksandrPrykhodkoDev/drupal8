<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\FooterCopyrightBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a footer copyright block.
 *
 * @Block(
 *   id = "footer_copyright_block",
 *   admin_label = @Translation("Footer copyright block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class FooterCopyrightBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::state();

    return array(
      '#type' => 'markup',
      '#markup' => '<div><span>' . $config->get('footer_settings.footer_copyrights') . '</span></div>',
    );
  }
}
