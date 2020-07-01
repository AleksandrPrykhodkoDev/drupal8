<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\ContactSettings.
 */

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Class ContactSettings.
 *
 * @package Drupal\ss_common\Form
 *
 * @ingroup ss_common
 */
class ContactSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contact_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.contact',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $field_names = [
      'title',
      'links_location',
      'links_service',
      'links_question',
      'links_confidential',
      'search_title',
      'search_text',
      'questions_title',
      'questions_text',
      'complaints_title',
      'complaints_text',
      'confidential_title',
      'confidential_text'
    ];

    foreach ($field_names as $field_name) {
      $value = $form_state->getValue($field_name);
      if (is_array($value)) {
        $value = $value['value'];
      }
      $config->set("contact.page.$field_name", $value);
    }

    $image_field_names = [
      'image',
      'questions_image',
      'complaints_file_1',
      'complaints_file_2',
      'complaints_file_3',
      'complaints_file_4',
      'complaints_file_5'
    ];

    foreach ($image_field_names as $field_name) {
      $value = $form_state->getValue($field_name);
      if (!empty($value)) {
        $file = File::load($value[0]);
        $file->setPermanent();
        $file->save();
        $file_usage = \Drupal::service('file.usage');
        $file_usage->add($file, 'ss_common', 'user', 1);
        $config->set("contact.page.$field_name", $value);
      }
      else {
        $config->delete("contact.page.$field_name");
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $form['title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('contact.page.title'),
    ];

    $links = [
      'links_location' => t('Contact met jouw locatie'),
      'links_service' => t('Contact met de klantenservice'),
      'links_question' => t('Vragen, suggesties en klachten'),
      'links_confidential' => t('Vertrouwenspersoon'),
    ];

    foreach ($links as $key => $link) {
      $form[$key] = [
        '#title' => t('Link: @field', ['@field' => $link]),
        '#type' => 'textfield',
        '#default_value' => $config->get("contact.page.$key"),
      ];
    }

    $form['image'] = [
      '#title' => t('Header image'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#default_value' => $config->get('contact.page.image')
    ];

    $form['search_title'] = [
      '#title' => t('Search title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('contact.page.search_title')
    ];

    $form['search_text'] = [
      '#title' => t('Search text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('contact.page.search_text')
    ];

    $form['questions_title'] = [
      '#title' => t('Suggestions and questions title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('contact.page.questions_title')
    ];

    $form['questions_text'] = [
      '#title' => t('Suggestions and questions text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('contact.page.questions_text')
    ];

    $form['questions_image'] = [
      '#title' => t('Suggestions and questions image'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#default_value' => $config->get('contact.page.questions_image')
    ];

    $form['complaints_title'] = [
      '#title' => t('Klachten title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('contact.page.complaints_title')
    ];

    $form['complaints_text'] = [
      '#title' => t('Klachten text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('contact.page.complaints_text')
    ];

    for ($i = 1; $i <= 5; $i++) {
      $form["complaints_file_$i"] = [
        '#title' => $i == 1 ? t('Klachten files') : NULL,
        '#type' => 'managed_file',
        '#upload_location' => 'public://',
        '#default_value' => $config->get("contact.page.complaints_file_$i"),
        '#upload_validators' => [
          'file_validate_extensions' => ['pdf']
        ]
      ];
    }

    $form['confidential_title'] = [
      '#title' => t('Vertrouwenspersoon title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('contact.page.confidential_title')
    ];

    $form['confidential_text'] = [
      '#title' => t('Vertrouwenspersoon text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('contact.page.confidential_text')
    ];

    return parent::buildForm($form, $form_state);
  }

}
