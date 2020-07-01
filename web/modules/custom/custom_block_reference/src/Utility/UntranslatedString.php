<?php

namespace Drupal\custom_block_reference\Utility;

/**
 * Wraps untranslated string method.
 */
trait UntranslatedString {

  /**
   * Returns untranslated category name.
   *
   * @param string|object $string
   *   Category name as string or translatable object.
   *
   * @return string
   *   Untranslated category name.
   */
  public function getUntranslatedString($string) {
    if (is_object($string)) {
      $string = $string->getUntranslatedString();
    }

    return $string;
  }

}
