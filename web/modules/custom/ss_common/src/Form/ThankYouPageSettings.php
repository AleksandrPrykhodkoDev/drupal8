<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\ThankYouPageSettings.
 */

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ThankYouPageSettings.
 *
 * @package Drupal\ss_common\Form
 *
 * @ingroup ss_common
 */
class ThankYouPageSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'thank_you_page_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.thank_you_page',
    ];
  }

  protected function getFieldsList() {
    return [
      'registration_kdv' => t('KDV registration'),
      'registration_bso' => t('BSO registration'),
      'registration_psz' => t('PSZ registration'),
      'tour_kdv' => t('KDV Tour'),
      'tour_bso' => t('BSO Tour'),
      'tour_psz' => t('PSZ Tour'),
      'question' => t('Question'),
      'suggestion' => t('Suggestion'),
      'complaint' => t('Complaint'),
      'moms' => t('Moms to be campaign'),
      'meeting' => t('Ocbijeenkomst'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $forms = $this->getFieldsList();
    foreach ($forms as $key => $label) {
      $value = $form_state->getValue($key);
      $config->set("thank_you_page.$key", $value);
    }

    $site_404 = $form_state->getValue('site_404');
    if ($site_404) {
      $site_404 = '/node/' . $site_404;
    }

    \Drupal::configFactory()->getEditable('system.site')->set('page.404', $site_404)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state();
    $site_config = $this->config('system.site');

    $page_404_args = explode('/', $site_config->get('page.404'));
    $page_404_nid = array_pop($page_404_args);
    $page_404 = NULL;
    if ($page_404_nid) {
      $page_404 = \Drupal::entityTypeManager()->getStorage('node')->load($page_404_nid);
    }

    $form['site_404'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => [
          'generic_page' => 'generic_page'
        ]
      ],
      '#title' => t('Default 404 (not found) page'),
      '#default_value' => $page_404,
      '#description' => t('This page is displayed when no other content matches the requested document. Leave blank to display a generic "page not found" page.'),
    );

    $forms = $this->getFieldsList();

    foreach ($forms as $key => $label) {
      $form[$key] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => [
          'target_bundles' => [
            'generic_page' => 'generic_page'
          ]
        ],
        '#name' => $key,
        '#default_value' => $config->get("thank_you_page.$key") ? \Drupal::entityTypeManager()->getStorage('node')->load($config->get("thank_you_page.$key")) : NULL,
        '#title' => $this->t("Thank you page for @label form" , ['@label' => $label]),
        '#description' => $this->t('Leave blank to display a default Thank you page'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

}
