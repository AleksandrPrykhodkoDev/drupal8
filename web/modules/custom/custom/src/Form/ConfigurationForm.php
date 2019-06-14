<?php

/**
 * @file
 * Contains \Drupal\custom\Form\ConfigurationForm.
 */

namespace Drupal\custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'custom.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('custom.settings');

    $form['fixed_price'] = [
      '#type' => 'number',
      '#title' => $this->t('Fixed price'),
      '#default_value' => $config->get('fixed_price') ?? 20,
      '#min' => 0,
    ];

    $form['var_price'] = [
      '#type' => 'number',
      '#title' => $this->t('Variable price'),
      '#default_value' => $config->get('var_price') ?? 100,
      '#min' => 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('custom.settings')
      ->set('fixed_price', $form_state->getValue('fixed_price'))
      ->set('var_price', $form_state->getValue('var_price'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
