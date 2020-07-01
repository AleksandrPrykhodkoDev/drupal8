<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\ServicesSettings.
 */

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SocialSettings.
 *
 * @package Drupal\ss_common\Form
 *
 * @ingroup ss_common
 */
class ServicesSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'services_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.services',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = \Drupal::state();
    $config->set('services.crm', $values['crm']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $form['crm'] = [
      '#title' => 'CRM link',
      '#type' => 'textfield',
      '#default_value' => $config->get('services.crm'),
      '#required' => TRUE
    ];

    return parent::buildForm($form, $form_state);
  }

}
