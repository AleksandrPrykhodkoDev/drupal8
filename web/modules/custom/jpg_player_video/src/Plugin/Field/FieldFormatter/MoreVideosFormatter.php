<?php

namespace Drupal\jpg_player_video\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'JPG - More Videos' formatter.
 *
 * @FieldFormatter(
 *   id = "jpg_player_video_more_videos",
 *   label = @Translation("JPG - More Videos"),
 *   description = @Translation("Displays more videos in video player."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MoreVideosFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      // Media Video
      if ($entity->hasField('field_media_video_file') &&
        !$entity->field_media_video_file->isEmpty() &&
        !$entity->field_cover_picture->isEmpty() &&
        !$entity->field_cover_picture->entity->field_media_image->isEmpty()) {

        $elements[$delta]['#attributes']['class'][] = 'more-video-data';
        $elements[$delta]['#attributes']['data-media-id'] = $entity->id();
        $elements[$delta]['#attributes']['data-field-media-video-file-url'] = $entity->field_media_video_file->entity->createFileUrl();
        $elements[$delta]['#attributes']['data-field-cover-picture-url'] = $entity->field_cover_picture->entity->field_media_image->entity->createFileUrl();
      }

      // Media Remote video
      if ($entity->hasField('field_media_oembed_video') &&
        !$entity->field_media_oembed_video->isEmpty() &&
        !$entity->field_cover_picture->isEmpty() &&
        !$entity->field_cover_picture->entity->field_media_image->isEmpty()) {

        $elements[$delta]['#attributes']['class'][] = 'more-video-data';
        $elements[$delta]['#attributes']['data-media-id'] = $entity->id();
        $elements[$delta]['#attributes']['data-field-media-oembed-video-file-url'] = $entity->field_media_oembed_video->value;
        $elements[$delta]['#attributes']['data-field-cover-picture-url'] = $entity->field_cover_picture->entity->field_media_image->entity->createFileUrl();
      }
    }

    $elements['#attached']['drupalSettings']['jpgPlayerVideoMoreVideos'] = [
      'ajaxEnabled' => (bool) $this->getSetting('ajax_enabled'),
      'viewMode' =>  $this->getSetting('view_mode_ajax_return'),
      'wrapperSelector' => $this->getSetting('wrapper_selector_to_replace'),
      'parentWrapperSelector' => $this->getSetting('parent_wrapper_selector'),
    ];

    $elements['#attached']['library'][] = 'jpg_player_video/more_videos';

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'view_mode_ajax_return' => 'default',
        'wrapper_selector_to_replace' => '',
        'parent_wrapper_selector' => '',
        'ajax_enabled' => FALSE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#title' => t('View mode'),
      '#default_value' => $this->getSetting('view_mode'),
      '#required' => TRUE,
    ];

    $elements['ajax_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable AJAX support'),
      '#description' => t('By clicking on entity returns rerendered content in the selected view mode.'),
      '#default_value' => $this->getSetting('ajax_enabled'),
    ];

    $elements['view_mode_ajax_return'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
      '#title' => t('View mode returned by Ajax'),
      '#default_value' => $this->getSetting('view_mode_ajax_return'),
      '#required' => TRUE,
    ];

    $elements['parent_wrapper_selector'] = [
      '#type' => 'textfield',
      '#title' => t('Parent CSS wrapper selector'),
      '#description' => t('Parent CSS wrapper selector to restrict area of replacement (e.g. paragraph class)'),
      '#default_value' => $this->getSetting('parent_wrapper_selector'),
    ];

    $elements['wrapper_selector_to_replace'] = [
      '#type' => 'textfield',
      '#title' => t('CSS wrapper selector to replace with'),
      '#description' => t('CSS wrapper selector to replace with content returned by Ajax'),
      '#default_value' => $this->getSetting('wrapper_selector_to_replace'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $view_modes = $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type'));
    $view_mode = $this->getSetting('view_mode');
    $summary[] = t('Rendered as @mode', ['@mode' => isset($view_modes[$view_mode]) ? $view_modes[$view_mode] : $view_mode]);
    $summary[] = t('Ajax support is @enabled', ['@enabled' => $this->getSetting('ajax_enabled') ? 'enabled' : 'disabled']);
    $view_mode = $this->getSetting('view_mode_ajax_return');
    $summary[] = t('Returned by Ajax as @mode', ['@mode' => isset($view_modes[$view_mode]) ? $view_modes[$view_mode] : $view_mode]);
    $summary[] = t('Parent CSS wrapper selector: @selector', ['@selector' => $this->getSetting('parent_wrapper_selector')]);
    $summary[] = t('CSS wrapper selector to replace: @selector', ['@selector' => $this->getSetting('wrapper_selector_to_replace')]);

    return $summary;
  }

}
