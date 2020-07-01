<?php

namespace Drupal\fj_master\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Alternative implementation of the 'string' formatter for node title field.
 *
 * @FieldFormatter(
 *   id = "string_with_selector",
 *   label = @Translation("Plain text with selector"),
 *   field_types = {
 *     "string",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class StringWithSelectorFormatter extends StringFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $options = parent::defaultSettings();

    $options['selector'] = 'span';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['selector'] = [
      '#type' => 'select',
      '#title' => $this->t('Selector'),
      '#default_value' => $this->getSetting('selector'),
      '#options' => [
        'span' => 'span',
        'div' => 'div',
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($this->getSetting('link_to_entity')) {
      $entity_type = $this->entityTypeManager->getDefinition($this->fieldDefinition->getTargetEntityTypeId());
      $summary[] = $this->t('Linked to the @entity_label', ['@entity_label' => $entity_type->getLabel()]);
    }
    if ($this->getSetting('selector')) {
      $summary[] = $this->t('Selector: @selector', ['@selector' => $this->getSetting('selector')]);
    }
    return $summary;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return array
   *   The textual output generated as a render array.
   */
  protected function viewValue(FieldItemInterface $item) {
    return [
      '#type' => 'inline_template',
      '#template' => '<{{ el }}>{{ value|nl2br }}</{{ el }}>',
      '#context' => [
        'value' => $item->value,
        'el' => $this->getSetting('selector'),
      ],
    ];
  }

}
