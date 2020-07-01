<?php

namespace Drupal\ss_location;

class GenericContent {

  /**
   * Get content by name.
   */
  public function getContent($name) {
    $query = \Drupal::database()->select('smallsteps_test_dashboard.GenericContent', 'gc');
    $query->fields('gc', ['Value']);
    $query->condition('gc.Name', $name, '=');
    $value = $query->execute()->fetchField();
    return $value;
  }

}
