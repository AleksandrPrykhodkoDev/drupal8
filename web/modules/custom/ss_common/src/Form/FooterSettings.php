<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\FooterSettings.
 */

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class FooterSettings.
 *
 * @package Drupal\ss_common\Form
 *
 * @ingroup ss_common
 */
class FooterSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'footer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.footer_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $field_names = [
      'main_footer' => [
        'footer_title',
        'search_title',
        'footer_copyrights'
      ],
      'location_footer' => [
        'location_footer_title',
        'location_footer_link',
        'location_footer_phone'
      ]
    ];

    foreach ($field_names as $section_name => $section) {
      $section_values = $form_state->getValue($section_name);
      foreach ($section as $field_name) {
        $value = $section_values[$field_name];
        if (is_array($value)) {
          $value = $value['value'];
        }
        $config->set("footer_settings.$field_name", $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $form['main_footer'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => t('Settings for main footer'),
    ];

    $form['main_footer']['footer_title'] = [
      '#title' => t('Title'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('footer_settings.footer_title')
    ];

    $form['main_footer']['search_title'] = [
      '#type' => 'textfield',
      '#title' => t('Title for search form'),
      '#default_value' => $config->get('footer_settings.search_title'),
    ];

    $form['main_footer']['footer_copyrights'] = [
      '#type' => 'textfield',
      '#title' => t('Copyrights'),
      '#default_value' => $config->get('footer_settings.footer_copyrights'),
    ];

    $form['location_footer'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => t('Settings for Location footer'),
    ];

    $form['location_footer']['location_footer_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('footer_settings.location_footer_title')
    ];

    $form['location_footer']['location_footer_link'] = [
      '#title' => t('Link title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('footer_settings.location_footer_link')
    ];

    $form['location_footer']['location_footer_phone'] = [
      '#title' => t('Phone number'),
      '#type' => 'textfield',
      '#default_value' => $config->get('footer_settings.location_footer_phone')
    ];

    return parent::buildForm($form, $form_state);
  }

}
