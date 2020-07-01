<?php

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds a Search Location Form.
 */
class CommonExposedFilter extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ss_common_exposed_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $vid = NULL) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);

    $options = $default = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
      $default[$term->tid] = $term->tid;
    }

    if (isset($_GET['category'])) {
      $default = $_GET['category'];
    }

    $form['category'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $default
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $categories = [];
    $category_values = $form_state->getValue('category');
    foreach ($category_values as $value) {
      if ($value) {
        $categories[$value] = $value;
      }
    }

    if (count($categories) == count($category_values)) {
      $categories = [];
    }

    $url = Url::fromRoute('<current>', ['category' => $categories]);
    return $form_state->setRedirectUrl($url);
  }

}
