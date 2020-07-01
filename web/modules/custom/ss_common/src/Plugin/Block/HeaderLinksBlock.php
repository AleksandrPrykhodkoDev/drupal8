<?php

/**
 * @file
 * Contains \Drupal\ss_common\Plugin\Block\HeaderLinksBlock.
 */

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a header buttons block.
 *
 * @Block(
 *   id = "header_links_block",
 *   admin_label = @Translation("Header links block"),
 *   category = @Translation("Smallsteps")
 * )
 */
class HeaderLinksBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_tree = \Drupal::menuTree();
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    $menu_name = 'smallsteps-header-top-menu';

    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = $menu_tree->load($menu_name, $parameters);

    $tree = $menu_tree->transform($tree, $manipulators);
    $menu = $menu_tree->build($tree);

    return array(
      '#type' => 'markup',
      '#markup' => \Drupal::service('renderer')->render($menu),
    );
  }
}
