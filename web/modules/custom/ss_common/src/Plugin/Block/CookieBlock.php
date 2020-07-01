<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\CookieBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block whit cookie notifications.
 *
 * @Block(
 *   id = "cookie_block",
 *   admin_label = @Translation("Cookie block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class CookieBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (isset($_COOKIE['opt'])) {
      return [];
    }

    $build['text'] = t('Als je onze site gebruikt krijg je van ons cookies.');
    $build['on'] = t('Vind ik goed');

    return $build;
  }
}
