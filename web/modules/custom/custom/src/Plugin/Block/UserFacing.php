<?php

namespace Drupal\custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'User-facing' block.
 *
 * @Block(
 *   id = "custom_user_facing",
 *   admin_label = @Translation("User-facing form"),
 *   category = @Translation("Custom")
 * )
 */
class UserFacing extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('\Drupal\custom\Form\UserFacingForm');
    return $form;
  }

}
