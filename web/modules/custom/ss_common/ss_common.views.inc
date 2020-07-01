<?php

/**
 * @file
 * Contains views hook for ss_common.
 */

/**
 * Implements hook_views_data_alter().
 */
function ss_common_views_data_alter(array &$data) {

  $data['taxonomy_term_data']['exclude_categories'] = [
    'title' => t('Exclude categories'),
    'group' => t('SmallSteps'),
    'argument' => [
      'id' => 'exclude_categories',
      'name field' => 'name',
      'numeric' => TRUE,
      'validate type' => 'tid',
    ],
  ];
}

