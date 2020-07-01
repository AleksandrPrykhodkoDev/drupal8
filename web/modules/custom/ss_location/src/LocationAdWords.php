<?php

namespace Drupal\ss_location;

class LocationAdWords {

  /**
   * Get location ad words.
   */
  public function getLocationAdWords($id) {
    $query = \Drupal::database()
      ->select('smallsteps_test_dashboard.AdWords', 'aw');
    $query->fields('aw', []);
    $query->condition('aw.Id', $id);
    $results = $query->execute()->fetchAssoc();

    return $results;
  }

}
