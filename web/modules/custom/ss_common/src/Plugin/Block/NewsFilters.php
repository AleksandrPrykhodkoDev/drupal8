<?php

namespace Drupal\ss_common\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\file\Entity\File;

/**
 * Provides a 'News filters' block.
 *
 * @Block(
 *   id = "ss_common_news_filters",
 *   admin_label = @Translation("News filters"),
 *   category = @Translation("Smallsteps")
 * )
 */
class NewsFilters extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\ss_common\Form\CommonExposedFilter', 'news');

    $build = [
      'filter' => [
        '#markup' => render($form)
      ]
    ];

    return $build;
  }
}
