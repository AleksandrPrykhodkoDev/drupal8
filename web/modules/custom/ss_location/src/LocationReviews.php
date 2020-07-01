<?php

namespace Drupal\ss_location;

class LocationReviews {

  /**
   * Get location ad words.
   */
  public function getLocationReviews($limit = 3) {
    $query = \Drupal::database()
      ->select('smallsteps_test_dashboard.Reviews', 'r');
    $query->fields('r', []);
    $query->range(0, $limit);
    $results = $query->execute()->fetchAll();

    return $results;
  }

}
