<?php

/**
 * @file
 * Contains \Drupal\ss_common\Form\FrontPageSettings.
 */

namespace Drupal\ss_common\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Class FrontPageSettings.
 *
 * @package Drupal\ss_common\Form
 *
 * @ingroup ss_common
 */
class FrontPageSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'frontpage_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ss_common.front_page',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = \Drupal::state();
    $config->set('front_page.top.title', $values['title']);
    $config->set('front_page.top.sub_title', $values['sub_title']);
    $config->set('front_page.bottom.banner_enable', $values['banner_enable']);

    if (is_array($values['text_bellow_image'])) {
      $values['text_bellow_image'] = $values['text_bellow_image']['value'];
    }
    $config->set('front_page.top.text_bellow_image', $values['text_bellow_image']);

    if (!empty($values['banner_image'])) {
      $file = File::load($values['banner_image'][0]);
      $file->setPermanent();
      $file->save();
      $file_usage = \Drupal::service('file.usage');
      $file_usage->add($file, 'ss_common', 'user', 1);
      $config->set('front_page.bottom.banner_image', $values['banner_image']);
    }

    if (!empty($values['top_image'])) {
      $file = File::load($values['top_image'][0]);
      $file->setPermanent();
      $file->save();
      $file_usage = \Drupal::service('file.usage');
      $file_usage->add($file, 'ss_common', 'user', 1);
      $config->set('front_page.top.top_image', $values['top_image']);
    }

    for ($i=1; $i<=3; $i++) {
      if (!empty($values["image_$i"])) {
        $file = File::load($values["image_$i"][0]);
        $file->setPermanent();
        $file->save();
        $file_usage = \Drupal::service('file.usage');
        $file_usage->add($file, 'ss_common', 'user', 1);
        $config->set("front_page.content.image_$i", $values["image_$i"]);
      }
      else {
        $config->set("front_page.content.image_$i", '');
      }

      $config->set("front_page.content.title_$i", $values["title_$i"]);

      if (is_array($values["text_$i"])) {
        $values["text_$i"] = $values["text_$i"]['value'];
      }
      $config->set("front_page.content.text_$i", $values["text_$i"]);

      $config->set("front_page.content.link_$i", $values["link_$i"]);
      $config->set("front_page.content.button_$i", $values["button_$i"]);
      $config->set("front_page.content.number_$i", $values["number_$i"]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::state();

    $form['top'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Top Block Text'),
    ];

    $form['top']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Main Title'),
      '#default_value' => $config->get('front_page.top.title'),
    ];

    $form['top']['sub_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sub Title'),
      '#default_value' => $config->get('front_page.top.sub_title'),
    ];

    $form['top']['top_image'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#title' => $this->t('Image'),
      '#default_value' => $config->get('front_page.top.top_image'),
    ];

    $form['bottom_text'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Bottom Block Text'),
    ];

    $form['bottom_text']['text_bellow_image'] = [
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#title' => $this->t('Text bellow image'),
      '#default_value' => $config->get('front_page.top.text_bellow_image'),
    ];

    $form['content'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content'),
    ];

    for ($i=1; $i<=3; $i++) {
      $form['content']["block_$i"] = [
        '#type' => 'fieldset',
        '#title' => 'Article',
      ];

      $form['content']["block_$i"]["number_$i"] = [
        '#type' => 'select',
        '#title' => $this->t('Select number'),
        '#options' => [
          '1' => $this->t('1'),
          '2' => $this->t('2'),
          '3' => $this->t('3'),
        ],
      ];

      $form['content']["block_$i"]["image_$i"] = [
        '#type' => 'managed_file',
        '#upload_location' => 'public://',
        '#title' => $this->t('Image'),
        '#default_value' => $config->get("front_page.content.image_$i"),
      ];

      $form['content']["block_$i"]["title_$i"] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title'),
        '#default_value' => $config->get("front_page.content.title_$i"),
      ];

      $form['content']["block_$i"]["text_$i"] = [
        '#type' => 'text_format',
        '#format' => 'full_html',
        '#title' => $this->t('Text'),
        '#default_value' => $config->get("front_page.content.text_$i"),
      ];

      $form['content']["block_$i"]["link_$i"] = [
        '#type' => 'textfield',
        '#title' => $this->t('Button link'),
        '#default_value' => $config->get("front_page.content.link_$i"),
      ];

      $form['content']["block_$i"]["button_$i"] = [
        '#type' => 'textfield',
        '#title' => $this->t('Button text'),
        '#default_value' => $config->get("front_page.content.button_$i"),
      ];
    }

    $form['bottom'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Bottom Banner Block'),
    ];

    $form['bottom']['banner_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Banner'),
      '#default_value' => $config->get('front_page.bottom.banner_enable'),
    ];

    $form['bottom']['banner_image'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://',
      '#title' => $this->t('Image'),
      '#default_value' => $config->get('front_page.bottom.banner_image'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
