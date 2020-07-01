<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\FooterSearchBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a footer search block.
 *
 * @Block(
 *   id = "footer_search_block",
 *   admin_label = @Translation("Footer search block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class FooterSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::state();
    $form = \Drupal::formBuilder()->getForm('Drupal\ss_common\Form\SearchLocation');

    $build = [
      'title' => [
        '#markup' => '<h2>' . $config->get('footer_settings.search_title') . '</h2>'
      ],
      'search' => [
        '#markup' => render($form)
      ]
    ];

    return $build;
  }
}
