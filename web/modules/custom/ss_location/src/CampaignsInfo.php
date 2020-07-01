<?php

namespace Drupal\ss_location;

class CampaignsInfo {

  /**
   * Get campaigns list.
   */
  public function getCampaigns() {
    $query = \Drupal::database()
      ->select('smallsteps_test_dashboard.Campaigns', 'c');
    $query->fields('c', ['Id', 'Name']);
    $query->condition('c.Status', 1, '=');
    $query->orderBy('c.Id', 'ASC');
    $values = $query->execute()->fetchAll();

    $campaigns = [];
    foreach ($values as $value) {
      $campaigns[$value->Id] = $value->Name;
    }

    return $campaigns;
  }

  /**
   * Get campaign services list.
   */
  public function getCampaignServices($cid) {
    $query = \Drupal::database()
      ->select('smallsteps_test_dashboard.CampaignsRel', 'cr');
    $query->leftJoin('smallsteps_test_dashboard.Services', 's', 's.Id = cr.Rel');
    $query->fields('s', ['Name']);
    $query->condition('cr.Campaign', $cid, '=');
    $query->condition('cr.Type', 1);
    $services = $query->execute()->fetchCol();

    return $services;
  }

  /**
   * Get campaign locations.
   */
  public function getCompaignLocations($cid) {
    $query = \Drupal::database()
      ->select('smallsteps_test_dashboard.Campaigns', 'c');
    $query->fields('c', ['Locations']);
    $query->condition('c.Status', 1, '=');
    $query->condition('c.Id', $cid, '=');
    $locations = $query->execute()->fetchField();

    $location_list = [];
    $locations_array = [];

    $locations = trim($locations);
    if ($locations) {
      $locations_array = explode(',', $locations);
    }

    if (count($locations_array) > 0) {
      foreach ($locations_array as $location_id) {
        $location_id  = trim($location_id);
        $query = \Drupal::database()
          ->select('smallsteps_test_dashboard.Locations', 'l');
        $query->fields('l', ['Name']);
        $query->condition('l.Status', 1, '=');
        $query->condition('l.Id', $location_id, '=');
        $location_name = $query->execute()->fetchField();
        if ($location_name) {
          $location_list[$location_id] = $location_name;
        }
      }
    }
    else {
      $query = \Drupal::database()
        ->select('smallsteps_test_dashboard.Locations', 'l');
      $query->fields('l', ['Id', 'Name']);
      $query->condition('l.Status', 1, '=');
      $query->orderBy('l.Name', 'ASC');
      $locations = $query->execute()->fetchAll();

      foreach ($locations as $location) {
        $location_list[$location->Id] = $location->Name;
      }
    }

    return $location_list;
  }

}
