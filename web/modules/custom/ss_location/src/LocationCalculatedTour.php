<?php

namespace Drupal\ss_location;

class LocationCalculatedTour {

  /**
   * Get tour calculate.
   */
  public function getCalculatedTour() {
    $query = \Drupal::database()
      ->select('smallsteps_prod_suitecrm.tour_tour', 'lead');
    $query->innerJoin('smallsteps_prod_suitecrm.tour_tour_cstm', 'leadcustom', 'leadcustom.id_c = lead.id');
    $query->addExpression('ROUND( COUNT( lead.id ) * 1.5 )');
    $query->condition('lead.deleted', 0, '=');
//    $query->condition('leadcustom.location_id_c', $location_id, '=');
    $query->where('YEAR( lead.date_entered ) = YEAR( NOW() - INTERVAL 1 WEEK )');
    $query->where('WEEKOFYEAR( lead.date_entered ) = WEEKOFYEAR( NOW() - INTERVAL 1 WEEK )');

    $count = $query->execute()->fetchField();

    return $count;
  }

}
