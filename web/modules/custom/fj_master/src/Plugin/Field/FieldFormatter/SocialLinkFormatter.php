<?php

namespace Drupal\fj_master\Plugin\Field\FieldFormatter;

use Drupal\Core\Render\Markup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'link' formatter.
 *
 * @FieldFormatter(
 *   id = "social_link",
 *   label = @Translation("Social Link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class SocialLinkFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $url = $this->buildUrl($item);
      $url_info = parse_url($url->getUri());
      $host = str_replace(['www.', '.com'], '', $url_info['host']);
      $user_name = substr($url_info['path'], strrpos($url_info['path'], '/') + 1);

      // TODO: check how work other icons, github, drupal etc.
      $icon = ($host == 'linkedin') ? '<i class="fab fa-linkedin-in"></i>' : '<i class="fab fa-' . $host . '"></i>';
      $title = ucfirst($host) . ' ' . $user_name;

      $element[$delta] = [
        '#type' => 'link',
        '#title' => Markup::create($icon),
        '#options' => $url->getOptions(),
      ];
      $element[$delta]['#url'] = $url;

      if (!empty($item->_attributes)) {
        $element[$delta]['#options'] += ['attributes' => []];
        $element[$delta]['#options']['attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }

      $element[$delta]['#options']['attributes']['class'][] = 'card-social-link';
      $element[$delta]['#options']['attributes']['title'] = $title;
    }

    return $element;
  }

}
