<?php

namespace Drupal\nunavut_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Nunavut: Core settings for this site.
 */
class PageSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nunavut_core_page_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['nunavut_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('nunavut_core.settings');
    $page_settings = $config->get('page_settings');

    $colors = _nunavut_core_load_allowed_values(
      'nunavut_core.settings',
      'paragraph_settings',
      'color_list'
    );
    $border_options = [];
    foreach ($colors as $color => $name) {
      $border_options['border-color-' . $color] = $name;
    }

    $form['page_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Page Defaults'),
      '#open' => TRUE,
    ];

    $form['page_settings']['background_image'] = [
      '#type' => 'media_library',
      '#allowed_bundles' => ['image'],
      '#title' => $this->t('Select default page header background'),
      '#default_value' => $page_settings['background_image'] ?? NULL,
      '#description' => $this->t('Upload or select default page header background image.'),
      '#cardinality' => -1 | 1,
    ];

    $form['page_settings']['content_bg_image'] = [
      '#type' => 'media_library',
      '#allowed_bundles' => ['image'],
      '#title' => $this->t('Select default page content background'),
      '#default_value' => $page_settings['content_bg_image'] ?? NULL,
      '#description' => $this->t('Upload or select default page content background image.'),
      '#cardinality' => -1 | 1,
    ];

    $form['page_settings']['page_border'] = [
      '#type' => 'select',
      '#title' => $this->t('Select default page border color'),
      '#options' => $border_options,
      '#default_value' => $page_settings['page_border'] ?? 'border-color-transparent',
      '#description' => $this->t('Select default page border color'),
    ];

    $form['#tree'] = TRUE;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('nunavut_core.settings')
      ->set('page_settings', $form_state->getValue('page_settings'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
