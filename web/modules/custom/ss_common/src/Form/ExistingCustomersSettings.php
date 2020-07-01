<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\ExistingCustomersSettings.
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
class ExistingCustomersSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'existing_customers_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.existing_customers',
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
      'teaching_plan_title',
      'teaching_plan_text',
      'health_title',
      'health_text',
      'health_files',
      'diet_title',
      'diet_text',
      'diet_files',
      'security_title',
      'security_text',
      'security_files',
      'protocol_title',
      'protocol_text',
      'report_title',
      'report_text',
      'participation_title',
      'participation_text',
      'questions_title',
      'questions_text',
      'complaints_title',
      'complaints_text',
      'complaints_files',
      'complaints_button_title',
      'complaints_button_link',
      'confidential_title',
      'confidential_text'
    ];

    foreach ($field_names as $field_name) {
      $value = $form_state->getValue($field_name);
      if (is_array($value)) {
        $value = $value['value'];
      }
      $config->set("existing_customers.page.$field_name", $value);
    }

    $image_field_names = [
      'image',
      'participation_image',
      'questions_image'
    ];

    foreach ($image_field_names as $field_name) {
      $value = $form_state->getValue($field_name);
      if (!empty($value)) {
        $file = File::load($value[0]);
        $file->setPermanent();
        $file->save();
        $file_usage = \Drupal::service('file.usage');
        $file_usage->add($file, 'ss_common', 'user', 1);
        $config->set("existing_customers.page.$field_name", $value);
      }
      else {
        $config->delete("existing_customers.page.$field_name");
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
      '#default_value' => $config->get('existing_customers.page.title'),
    ];

    $form['image'] = [
      '#title' => t('Header image'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#default_value' => $config->get('existing_customers.page.image')
    ];

    $form['search_title'] = [
      '#title' => t('Search title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.search_title')
    ];

    $form['search_text'] = [
      '#title' => t('Search text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.search_text')
    ];

    $form['teaching_plan'] = [
      '#type' => 'fieldset',
      '#title' => t('Pedagogisch werkplan sectie')
    ];

    $form['teaching_plan']['teaching_plan_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.teaching_plan_title')
    ];

    $form['teaching_plan']['teaching_plan_text'] = [
      '#title' => t('Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.teaching_plan_text')
    ];

    $form['health'] = [
      '#type' => 'fieldset',
      '#title' => t('Gezondheidsbeleid sectie')
    ];

    $form['health']['health_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.health_title')
    ];

    $form['health']['health_text'] = [
      '#title' => t('Left-side Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.health_text')
    ];

    $form['health']['health_files'] = [
      '#title' => t('Right-side text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.health_files')
    ];

    $form['diet'] = [
      '#type' => 'fieldset',
      '#title' => t('Voeding en beweging sectie')
    ];

    $form['diet']['diet_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.diet_title')
    ];

    $form['diet']['diet_text'] = [
      '#title' => t('Left-side Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.diet_text')
    ];

    $form['diet']['diet_files'] = [
      '#title' => t('Right-side text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.diet_files')
    ];

    $form['security'] = [
      '#type' => 'fieldset',
      '#title' => t('Veiligheidsbeleid sectie')
    ];

    $form['security']['security_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.security_title')
    ];

    $form['security']['security_text'] = [
      '#title' => t('Left-side Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.security_text')
    ];

    $form['security']['security_files'] = [
      '#title' => t('Right-side text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.security_files')
    ];

    $form['protocol'] = [
      '#type' => 'fieldset',
      '#title' => t('Protocol veilig slapen sectie')
    ];

    $form['protocol']['protocol_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.protocol_title')
    ];

    $form['protocol']['protocol_text'] = [
      '#title' => t('Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.protocol_text')
    ];

    $form['report'] = [
      '#type' => 'fieldset',
      '#title' => t('GGD rapport sectie')
    ];

    $form['report']['report_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.report_title')
    ];

    $form['report']['report_text'] = [
      '#title' => t('Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.report_text')
    ];

    $form['participation'] = [
      '#type' => 'fieldset',
      '#title' => t('Medezeggenschap en oudercommissie sectie')
    ];

    $form['participation']['participation_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.participation_title')
    ];

    $form['participation']['participation_text'] = [
      '#title' => t('Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.participation_text')
    ];

    $form['participation']['participation_image'] = [
      '#title' => t('Image'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#default_value' => $config->get('existing_customers.page.participation_image')
    ];

    $form['questions'] = [
      '#type' => 'fieldset',
      '#title' => t('Suggesties en vragen sectie')
    ];

    $form['questions']['questions_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.questions_title')
    ];

    $form['questions']['questions_text'] = [
      '#title' => t('Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.questions_text')
    ];

    $form['questions']['questions_image'] = [
      '#title' => t('Image'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#default_value' => $config->get('existing_customers.page.questions_image')
    ];

    $form['complaints'] = [
      '#type' => 'fieldset',
      '#title' => t('Klachten sectie')
    ];

    $form['complaints']['complaints_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.complaints_title')
    ];

    $form['complaints']['complaints_text'] = [
      '#title' => t('Left-side Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.complaints_text')
    ];

    $form['complaints']['complaints_files'] = [
      '#title' => t('Right-side text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.complaints_files')
    ];

    $form['complaints']['complaints_button_title'] = [
      '#title' => t('Right-side button title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.complaints_button_title')
    ];

    $form['complaints']['complaints_button_link'] = [
      '#title' => t('Right-side button link'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.complaints_button_link')
    ];

    $form['confidential'] = [
      '#type' => 'fieldset',
      '#title' => t('Vertrouwenspersoon sectie')
    ];

    $form['confidential']['confidential_title'] = [
      '#title' => t('Title'),
      '#type' => 'textfield',
      '#default_value' => $config->get('existing_customers.page.confidential_title')
    ];

    $form['confidential']['confidential_text'] = [
      '#title' => t('Text'),
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#default_value' => $config->get('existing_customers.page.confidential_text')
    ];

    return parent::buildForm($form, $form_state);
  }

}
