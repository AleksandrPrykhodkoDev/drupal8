<?php

namespace Drupal\custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;

class UserFacingForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_facing_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $ajax_wrapper = 'ajax-wrapper';

    $form['Name'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => $this->t('Name'),
      ],
      '#required' => TRUE,
    ];

    $form['Age'] = [
      '#type' => 'select',
      '#empty_value' => '',
      '#empty_option' => $this->t('- Age -'),
      '#options' => [
        '<20',
        '20-24',
        '25+',
      ],
      '#ajax' => [
        'callback' => [$this, 'ajaxSubmit'],
        'event' => 'change',
        'wrapper' => $ajax_wrapper,
      ],
      '#required' => TRUE,
    ];

    $form['CarSize'] = [
      '#type' => 'select',
      '#empty_value' => '',
      '#empty_option' => $this->t('- Car size -'),
      '#options' => [
        $this->t('small'),
        $this->t('medium'),
        $this->t('large'),
      ],
      '#ajax' => [
        'callback' => [$this, 'ajaxSubmit'],
        'event' => 'change',
        'wrapper' => $ajax_wrapper,
      ],
      '#required' => TRUE,
    ];

    $form['TotalPrice'] = [
      '#type' => 'textfield',
      '#value' => '',
      '#attributes' => [
        'placeholder' => $this->t('Total price'),
        'readonly' => 'readonly',
        'id' => $ajax_wrapper,
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('Age') == 0) {
      $form_state->setErrorByName('age', $this->t('You must be at least 20 years old.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $result = $this->getTotalPrice($form_state);

    $fields = array(
      'name' => $form_state->getValue('Name'),
      'total_price' => $result,
    );
    $query = \Drupal::database();
    $query ->insert('user_facing')
      ->fields($fields)
      ->execute();

    \Drupal::messenger()->addMessage('Your has been successfully submitted.');
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmit(array $form, FormStateInterface $form_state) {
    $result = $this->getTotalPrice($form_state);

    if ($form_state->getValue('Age') == 0) {
      $response = new AjaxResponse();
      $response->addCommand(new AlertCommand('You must be at least 20 years old.'));
      return $response;
    }

    if (isset($result)) {
      $form['TotalPrice']['#value'] = '$' . $result;
      $form_state->setRebuild(TRUE);
    }

    return $form['TotalPrice'];
  }

  /**
   * Total price.
   */
  public function getTotalPrice($form_state) {
    $configuration_form = \Drupal::config('custom.settings');
    $fixed_price = $configuration_form->get('fixed_price');
    $var_price = $configuration_form->get('var_price');

    $age = $this->getSelectAgeValues($form_state->getValue('Age'));
    $carsize = $this->getSelectCarSizeValues($form_state->getValue('CarSize'));

    if (is_numeric($carsize) && is_numeric($age)) {
      $result = $fixed_price + $var_price * (1 + $age + $carsize);
      return $result;
    }

    return NULL;
  }

  /**
   * Get age values.
   */
  public function getSelectAgeValues($key) {
    $values = [
      0 => 0,
      1 => '0.2',
      2 => 0,
    ];

    return $values[$key];
  }

  /**
   * Get car size values.
   */
  public function getSelectCarSizeValues($key) {
    $values = [
      0 => 0,
      1 => '0.5',
      2 => 1,
    ];

    return $values[$key];
  }

}
