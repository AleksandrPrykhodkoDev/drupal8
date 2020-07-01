<?php

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds a Search Location Form.
 */
class FooterSearch extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ss_common_footer_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $theme_path = drupal_get_path('theme', 'smallsteps');

    $form['search'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => 'zoek op plaats of postcode',
      ]
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
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
