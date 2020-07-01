<?php

namespace Drupal\fj_master\Plugin\metatag\Tag;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a custom plugin for the 'schema_item_list_element' meta tag.
 *
 *  This plugin convert menu name to items list with site navigation links.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_item_list_custom_navigation",
 *   label = @Translation("itemListElement"),
 *   description = @Translation("Site Navigation"),
 *   name = "itemListElement",
 *   group = "schema_item_list",
 *   weight = 20,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaItemListItemNavigation extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#description'] = $this->t('<b style="color: red;">[Custom] Use this field instead of above!</b> To create a list, provide a menu machine name (Example - "main" menu).');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function outputValue($input_value) {
    $items = [];
    $values = static::getItems($input_value);
    if (!empty($values) && is_array($values)) {
      foreach ($values as $key => $value) {
        $items[] = [
          '@type' => 'SiteNavigationElement',
          'position' => $key,
          'name' => $value['name'],
          'url' => $value['url'],
        ];
      }
    }
    return $items;
  }

  /**
   * Process the input value into an array of items.
   *
   * Each type of ItemList can extend this to process the input value into a
   * list of items.
   */
  public static function getItems($input_value) {
    $result = [];
    $links = \Drupal::menuTree()->load($input_value, new MenuTreeParameters());

    if (!$links) {
      return [];
    }

    foreach ($links as $menu_link_content) {
      $result[] = [
        'name' => $menu_link_content->link->getTitle(),
        'url' => $menu_link_content->link->getUrlObject()->setAbsolute()->toString(),
      ];
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    return static::testDefaultValue(3, ',');
  }

}
