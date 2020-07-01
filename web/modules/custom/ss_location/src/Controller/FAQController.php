<?php

namespace Drupal\ss_location\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for faq entity view.
 */
class FAQController extends ControllerBase {

  public function page() {

    $categories = [
      //1 => 'Landingspagina locatie',
      2 => 'Aanmelden en plaatsing',
      3 => 'Betalingen en facturen',
      4 => 'Kinderopvangtoeslag',
      5 => 'Diversen',
      //6 => 'Bestaande klant'
    ];

    $faqs_list = [];
    foreach ($categories as $id => $category) {

      $query = \Drupal::database()->select('smallsteps_test_dashboard.FAQsRel', 'fr');
      $query->join('smallsteps_test_dashboard.FAQs', 'fs', 'fs.Id = fr.FAQ');
      $query->addField('fr', 'FAQ');
      $query->condition('fr.Rel', $id);
      $query->condition('fr.Type', 2);
      $query->orderBy('fs.FAQOrder');
//      $query->range(0, 10);
      $ids = $query->execute()->fetchCol();

      $faq_list = \Drupal::entityTypeManager()->getStorage('ss_faq')->loadMultiple($ids);

      $list = [];
      foreach ($faq_list as $faq) {
        $list[] = [
          'question' => $faq->getQuestion(),
          'answer' => $faq->getAnswer()
        ];
      }

      $faqs_list[] = [
        'name' => $category,
        'list' => $list
      ];
    }

    return [
      '#theme' => 'ss_location_faq_page',
      '#faqs' => $faqs_list
    ];
  }
}
