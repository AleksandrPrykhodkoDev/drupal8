<?php

namespace Drupal\fj_master\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides a 'Latest blog posts' block with exclude settings.
 *
 * @Block(
 *  id = "fj_latest_blog_posts_block",
 *  admin_label = @Translation("Latest blog posts"),
 * )
 */
class LatestBlogPostsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'featured' => NULL,
      'most_popular_1' => NULL,
      'most_popular_2' => NULL,
      'most_popular_3' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['exclude'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exclude from results:'),
    ];
    $form['exclude']['featured'] = ['#title' => $this->t('Featured blog post')];
    $form['exclude']['most_popular_1'] = ['#title' => $this->t('Most popular #1')];
    $form['exclude']['most_popular_2'] = ['#title' => $this->t('Most popular #2')];
    $form['exclude']['most_popular_3'] = ['#title' => $this->t('Most popular #3')];

    $elements_keys = Element::children($form['exclude']);
    foreach ($elements_keys as $key) {
      $autocomplete_data = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_handler' => 'default',
        '#selection_settings' => ['target_bundles' => ['blog_post']],
        // Note: we use process_default_value to simplify submit, this is a
        // reason why on second form load we will see only ID without title.
        '#process_default_value' => FALSE,
        '#default_value' => $this->configuration[$key],
      ];
      $form['exclude'][$key] = array_merge($form['exclude'][$key], $autocomplete_data);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Process the block's submission handling if no errors occurred only.
    if (!$form_state->getErrors()) {
      $values = $form_state->getValues();
      foreach ($values['exclude'] as $key => $value) {
        if (!$value) {
          continue;
        }

        $this->configuration[$key] = $value;
      }
    }

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $arguments = [
      $this->configuration['featured'],
      $this->configuration['most_popular_1'],
      $this->configuration['most_popular_2'],
      $this->configuration['most_popular_3'],
    ];
    $arguments = array_filter($arguments);

    return [
      '#type' => 'view',
      '#name' => 'latest_blog_posts',
      '#display_id' => 'latest_blog_posts',
      '#arguments' => !empty($arguments) ? ['nid' => implode(',', array_filter($arguments))] : [],
    ];
  }

}
