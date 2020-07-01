<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\MeetingSettings.
 */

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Class MeetingSettings.
 *
 * @package Drupal\ss_common\Form
 *
 * @ingroup ss_common
 */
class MeetingSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'meeting_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.meeting',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $field_names = [
      'text'
    ];

    foreach ($field_names as $field_name) {
      $value = $form_state->getValue($field_name);
      if (is_array($value)) {
        $value = $value['value'];
      }
      $config->set("meeting.page.$field_name", $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $form['text'] = [
      '#title' => t('Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('meeting.page.text')
    ];

    return parent::buildForm($form, $form_state);
  }

}
