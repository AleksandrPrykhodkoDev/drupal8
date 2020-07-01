<?php

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds a Search Location Form.
 */
class SearchLocation extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ss_common_search_location_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $placeholder = NULL, $referer = NULL) {
    $theme_path = drupal_get_path('theme', 'smallsteps');

    $form['#attached']['library'][] = 'ss_common/search';

    $form['search'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => $placeholder ? $placeholder : t('zoek op plaats of postcode'),
      ]
    ];

    $form['searchtype'] = [
      '#type' => 'hidden',
      '#default_value' => 'locatieadres'
    ];

    $form['referer'] = [
      '#type' => 'hidden',
      '#default_value' => $referer
    ];

    $form['submit'] = [
      '#type' => 'image_button',
      '#src' => $theme_path . '/images/search2.png',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $search = $form_state->getValue('search');

    $query = [];

    $referer = $form_state->getValue('referer');
    if (isset($referer) && !empty($referer)) {
      $query['referer'] = $referer;

      if ($referer == 'textsearch') {
        $query = [
          'search_api_fulltext' => $search,
        ];
        $form_state->setRedirect('view.zoeken.zoeken', [], ['query' => $query]);
      }
      else {
        $args = [];
        if (strpos($search, ',') !== FALSE) {
          list($city, $province) = explode(',', $search);
          $city = strtolower($city);
          $province = strtolower($province);
          $args['city'] = trim($city);
          $args['province'] = trim($province);
        }
        elseif (preg_match('/^\d{4}/', $search)) {
          $search = urldecode(preg_replace('/^(\d{4})\s*?(\w{2})(.*)/', '$1 $2', strtoupper($search)));
          $query['Postcode'] = $search;
        }
        elseif (strpos($search, '-') !== FALSE) {
          list($location) = explode('-', $search);
          $query['Name'] = trim($location);
        }
        else {
          $query['Name'] = trim($search);
        }
        $form_state->setRedirect('entity.ss_location.search', $args, ['query' => $query]);
      }
    }
    else {
      $args = [];
      if (strpos($search, ',') !== FALSE) {
        list($city, $province) = explode(',', $search);
        $city = strtolower($city);
        $province = strtolower($province);
        $args['city'] = trim($city);
        $args['province'] = trim($province);
      }
      elseif (preg_match('/^\d{4}/', $search)) {
        $search = urldecode(preg_replace('/^(\d{4})\s*?(\w{2})(.*)/', '$1 $2', strtoupper($search)));
        $query['Postcode'] = $search;
      }
      elseif (strpos($search, '-') !== FALSE) {
        list($location) = explode('-', $search);
        $query['Name'] = trim($location);
      }
      else {
        $query['Name'] = trim($search);
      }
      $form_state->setRedirect('entity.ss_location.search', $args, ['query' => $query]);
    }
  }

}
