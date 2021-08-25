<?php

namespace Drupal\asf_common\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;

/**
 * Plugin implementation of the 'text_summary_or_trimmed_add_dots' formatter.
 *
 * @FieldFormatter(
 *   id = "text_summary_or_trimmed_add_dots",
 *   label = @Translation("Summary or trimmed add dots"),
 *   field_types = {
 *     "text_with_summary"
 *   },
 *   quickedit = {
 *     "editor" = "form"
 *   }
 * )
 */
class TextSummaryOrTrimmedFormatterAddDots extends TextTrimmedFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $render_as_summary = function (&$element) {
      // Make sure any default #pre_render callbacks are set on the element,
      // because text_pre_render_summary() must run last.
      $element += \Drupal::service('element_info')->getInfo($element['#type']);
      // Add the #pre_render callback that renders the text into a summary.
      $element['#pre_render'][] = [TextTrimmedFormatter::class, 'preRenderSummary'];
      // Pass on the trim length to the #pre_render callback via a property.
      $element['#text_summary_trim_length'] = $this->getSetting('trim_length');
    };

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => NULL,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];

      if ($this->getPluginId() == 'text_summary_or_trimmed_add_dots' && !empty($item->summary)) {
        $elements[$delta]['#text'] = $item->summary;

      }
      else {
        $elements[$delta]['#text'] = $item->value;
        $render_as_summary($elements[$delta]);
      }

      // Add dots.
      if (mb_strlen($elements[$delta]['#text']) >= $this->getSetting('trim_length')
        && $this->getSetting('trim_length') > 4) {
        $elements[$delta]['#text'] = mb_substr(
          $elements[$delta]['#text'],
          0,
          $this->getSetting('trim_length') - 3) . '...';
      }
    }

    return $elements;
  }

}
