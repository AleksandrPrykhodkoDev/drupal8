<?php

namespace Drupal\nunavut_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm - Config form for nunavut_core.
 *
 * @package Drupal\nunavut_core\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->loggerFactory = $container->get('logger.factory');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'nunavut_core.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // 1. Special extracting for the CrowdRiff code.
    $crowdriff_settings = $form_state->getValue('crowdriff_settings');
    if (isset($crowdriff_settings['gallery_code'])) {
      preg_match(
        '/[[:alnum:]]+$/',
        trim($crowdriff_settings['gallery_code']),
        $crowdriff_matches
      );

      $crowdriff_settings['gallery_code'] = $crowdriff_matches[0] ?? NULL;
    }

    // 2. Other settings.
    $this->config('nunavut_core.settings')
      ->set('paragraph_settings', $form_state->getValue('paragraph_settings'))
      ->set('paragraph_settings_button', $form_state->getValue('paragraph_settings_button'))
      ->set('paragraph_settings_container', $form_state->getValue('paragraph_settings_container'))
      ->set('paragraph_settings_image', $form_state->getValue('paragraph_settings_image'))
      ->set('paragraph_settings_card', $form_state->getValue('paragraph_settings_card'))
      ->set('crowdriff_settings', $crowdriff_settings)
      ->set('search_settings', $form_state->getValue('search_settings'))
      ->set('weather_settings', $form_state->getValue('weather_settings'))
      ->set('mapbox_gl_settings', $form_state->getValue('mapbox_gl_settings'))
      ->set('covid_page', $form_state->getValue('covid_page'))
      ->set('cards_settings', $form_state->getValue('cards_settings'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('nunavut_core.settings');

    $this->paragraphFormSection($form, $config);
    $this->paragraphFormSectionButton($form, $config);
    $this->paragraphFormSectionContainer($form, $config);
    $this->paragraphFormSectionImage($form, $config);
    $this->paragraphFormSectionCard($form, $config);

    $this->crowdRiffFormSection($form, $config);

    $this->searchFormSection($form, $config);
    $this->yahooWeatherSection($form, $config);
    $this->mapboxGlSection($form, $config);
    $this->covidPageSection($form, $config);
    $this->cardsSettings($form, $config);

    $form['#tree'] = TRUE;

    return parent::buildForm($form, $form_state);
  }

  /**
   * Build 'Paragraph settings' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function paragraphFormSection(array &$form, $config) {
    $paragraph_settings = $config->get('paragraph_settings');

    $form['paragraph_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Entity Settings'),
      '#open' => FALSE,
    ];

    $form['paragraph_settings']['target_field'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of values for target field'),
      '#default_value' => $paragraph_settings['target_field'],
    ];

    $form['paragraph_settings']['classes_field'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of values for classes field'),
      '#default_value' => $paragraph_settings['classes_field'],
    ];

    $form['paragraph_settings']['color_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of colors'),
      '#default_value' => $paragraph_settings['color_list'],
    ];

    $form['paragraph_settings']['border_width_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of border width'),
      '#default_value' => $paragraph_settings['border_width_list'],
    ];

    $form['paragraph_settings']['space_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of spaces'),
      '#default_value' => $paragraph_settings['space_list'],
    ];

    $form['paragraph_settings']['padding_list'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of padding'),
      '#default_value' => $paragraph_settings['padding_list'],
    ];

    $form['paragraph_settings']['opacity'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of opacity'),
      '#default_value' => $paragraph_settings['opacity'],
    ];

    $form['paragraph_settings']['horizontal_align'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of align'),
      '#default_value' => $paragraph_settings['horizontal_align'],
    ];

    $form['paragraph_settings']['vertical_align'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of vertical align'),
      '#default_value' => $paragraph_settings['vertical_align'],
    ];
  }

  /**
   * Build 'Paragraph settings' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function paragraphFormSectionButton(array &$form, $config) {
    $paragraph_settings = $config->get('paragraph_settings_button');

    $form['paragraph_settings_button'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph Button Settings'),
      '#open' => FALSE,
    ];

    $form['paragraph_settings_button']['button_type'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of values for button_type field'),
      '#default_value' => $paragraph_settings['button_type'],
    ];

    $form['paragraph_settings_button']['icon_position'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of values for icon_position field'),
      '#default_value' => $paragraph_settings['icon_position'],
    ];
  }

  /**
   * Build 'Paragraph settings' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function paragraphFormSectionContainer(array &$form, $config) {
    $paragraph_settings = $config->get('paragraph_settings_container');

    $form['paragraph_settings_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph Container Settings'),
      '#open' => FALSE,
    ];

    $form['paragraph_settings_container']['container_type'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of values for container_type field'),
      '#default_value' => $paragraph_settings['container_type'],
    ];

    $form['paragraph_settings_container']['container_tag'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of values for container_tag field'),
      '#default_value' => $paragraph_settings['container_tag'],
    ];
  }

  /**
   * Build 'Paragraph settings image' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function paragraphFormSectionImage(array &$form, $config) {
    $paragraph_settings = $config->get('paragraph_settings_image');

    $form['paragraph_settings_image'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph Image Settings'),
      '#open' => FALSE,
    ];

    $form['paragraph_settings_image']['description_position'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of positions'),
      '#default_value' => $paragraph_settings['description_position'],
    ];
  }

  /**
   * Build 'Paragraph settings card' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function paragraphFormSectionCard(array &$form, $config) {
    $paragraph_settings = $config->get('paragraph_settings_card');

    $form['paragraph_settings_card'] = [
      '#type' => 'details',
      '#title' => $this->t('Paragraph Card Settings'),
      '#open' => FALSE,
    ];

    $form['paragraph_settings_card']['card_type'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of card types'),
      '#default_value' => $paragraph_settings['card_type'],
    ];
  }

  /**
   * Build 'CrowdRiff' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function crowdRiffFormSection(array &$form, $config) {
    $crowdriff_settings = $config->get('crowdriff_settings');

    $form['crowdriff_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('CrowdRiff Gallery Settings'),
      '#open' => FALSE,
    ];

    $form['crowdriff_settings']['gallery_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gallery Code'),
      '#default_value' => $crowdriff_settings['gallery_code'],
    ];
  }

  /**
   * Build 'search' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function searchFormSection(array &$form, $config) {
    $settings = $config->get('search_settings');

    $form['search_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Search Settings'),
      '#open' => FALSE,
    ];

    $pages = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => 'page']);
    $options_page = ['_none' => $this->t('None')];
    foreach ($pages as $page) {
      $options_page[$page->id()] = $page->label();
    }

    $form['search_settings']['page'] = [
      '#type' => 'select',
      '#title' => $this->t('Search Page Node'),
      '#options' => $options_page,
      '#default_value' => $settings['page'] ?? '_none',
    ];
  }

  /**
   * Build 'covid' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function covidPageSection(array &$form, $config) {
    $settings = $config->get('covid_page');

    $form['covid_page'] = [
      '#type' => 'details',
      '#title' => $this->t('Covid Page'),
      '#open' => FALSE,
    ];

    $form['covid_page']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Covid page Url'),
      '#default_value' => $settings['url'] ?? '',
    ];
  }

  /**
   * Build 'weather' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function yahooWeatherSection(array &$form, $config) {
    $settings = $config->get('weather_settings');

    $form['weather_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Weather Settings'),
      '#open' => FALSE,
      '#description' => $this->t('If you don\'t have a Yahoo App, please <a href="@yahoo-app" target="_blank">create</a>', [
        '@yahoo-app' => 'https://developer.yahoo.com/apps/',
      ]),
    ];

    $form['weather_settings']['app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Yahoo App ID'),
      '#required' => TRUE,
      '#default_value' => $settings['app_id'] ?? '',
      '#description' => $this->t('Please enter your Yahoo App ID.'),
    ];

    $form['weather_settings']['consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Yahoo App Consumer Key'),
      '#required' => TRUE,
      '#default_value' => $settings['consumer_key'] ?? '',
      '#description' => $this->t('Please enter your Yahoo App Consumer Key.'),
    ];

    $form['weather_settings']['consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Yahoo App Consumer Secret Key'),
      '#required' => TRUE,
      '#default_value' => $settings['consumer_secret'] ?? '',
      '#description' => $this->t('Please enter your Yahoo App Consumer Secret Key.'),
    ];
  }

  /**
   * Build 'search' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function mapboxGlSection(array &$form, $config) {
    $settings = $config->get('mapbox_gl_settings');

    $form['mapbox_gl_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Mapbox GL Settings'),
      '#open' => FALSE,
    ];

    $form['mapbox_gl_settings']['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mapbox Access token'),
      '#default_value' => $settings['access_token'] ?? '',
      '#description' => $this->t('Please enter your Mapbox Access token.'),
    ];

    $form['mapbox_gl_settings']['style'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mapbox Style'),
      '#default_value' => $settings['style'] ?? '',
      '#description' => $this->t('Please enter your Mapbox style link.'),
    ];

    $form['mapbox_gl_settings']['lat'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Latitude'),
      '#default_value' => $settings['lat'] ?? '',
      '#description' => $this->t('Please enter your default latitude.'),
    ];

    $form['mapbox_gl_settings']['lng'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Longitude'),
      '#default_value' => $settings['lng'] ?? '',
      '#description' => $this->t('Please enter your default longitude.'),
    ];
  }

  /**
   * Build 'cards' config form section.
   *
   * @param array $form
   *   Renderable array.
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   *   Config instance.
   */
  private function cardsSettings(array &$form, $config) {
    $settings = $config->get('cards_settings');

    $form['cards_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Cards settings'),
      '#open' => FALSE,
    ];

    $form['cards_settings']['package_more_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Package read more label'),
      '#default_value' => $settings['package_more_label'] ?? '',
    ];

    $form['cards_settings']['operator_more_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Operator read more label'),
      '#default_value' => $settings['operator_more_label'] ?? '',
    ];

    $form['cards_settings']['story_more_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Story read more label'),
      '#default_value' => $settings['story_more_label'] ?? '',
    ];

    $form['cards_settings']['page_more_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page read more label'),
      '#default_value' => $settings['page_more_label'] ?? '',
    ];
  }

}
