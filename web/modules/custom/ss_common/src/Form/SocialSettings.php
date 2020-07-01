<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\SocialSettings.
 */

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Class SocialSettings.
 *
 * @package Drupal\ss_common\Form
 *
 * @ingroup ss_common
 */
class SocialSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.social',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = \Drupal::state();
    foreach ($this->getSocial() as $key => $name) {
      $config->set('social.' . $key . '.link', $values[$key]['link']);
      if (!empty($values[$key]['image'])) {
        $file = File::load($values[$key]['image'][0]);
        $file->setPermanent();
        $file->save();
        $file_usage = \Drupal::service('file.usage');
        $file_usage->add($file, 'ss_common', 'user', 1);
        $config->set('social.' . $key . '.image', $values[$key]['image']);
      }
      else {
        $config->delete('social.' . $key . '.image');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    foreach ($this->getSocial() as $key => $name) {
      $form[$key] = [
        '#type' => 'fieldset',
        '#tree' => TRUE,
        '#title' => $this->t('@name settings', ['@name' => $name]),
      ];

      $form[$key]['image'] = [
        '#type' => 'managed_file',
        '#upload_location' => 'public://',
        '#default_value' => $config->get('social.' . $key . '.image'),
      ];

      $form[$key]['link'] = [
        '#type' => 'textfield',
        '#default_value' => $config->get('social.' . $key . '.link'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Build a list of social media.
   */
  public static function getSocial() {
    $social = [
      'facebook' => t('Facebook'),
      'twitter' => t('Twitter'),
      'google' => t('Google'),
      'pinterest' => t('Pinterest'),
      'linked' => t('LinkedIn'),
    ];
    return $social;
  }

}
