<?php

namespace Drupal\real_id_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'id_field_widget' field widget.
 *
 * @FieldWidget(
 *   id = "id_field_default",
 *   label = @Translation("ID field default"),
 *   field_types = {"id_field"},
 * )
 */
class IdFieldDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => isset($items[$delta]->value)
      ? $items[$delta]->value
      : NULL,
      '#placeholder' => $this->t('This field will be self-generated'),
      '#disabled' => TRUE,
    ];

    return $element;
  }

}
