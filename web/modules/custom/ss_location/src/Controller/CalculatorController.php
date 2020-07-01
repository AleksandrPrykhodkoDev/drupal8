<?php

namespace Drupal\ss_location\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for faq entity view.
 */
class CalculatorController extends ControllerBase {

  public function page() {

    ob_start();
    include (__DIR__ . "/../../../../../form-nettokosten/include.php");
    $calculator_content = ob_get_contents();
    ob_end_clean();

    return [
      '#type' => 'inline_template',
      '#template' => "{{ calculator_content | raw }}",
      '#context' => [
        'calculator_content' => $calculator_content,
      ]
    ];
  }
}
