<?php

namespace Drupal\nunavut_core\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "pastefromgdocs" plugin.
 *
 * @CKEditorPlugin(
 *   id = "pastefromgdocs",
 *   label = @Translation("Paste from Google Docs"),
 * )
 */
class PasteFromGoogleDoc extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return 'libraries/ckeditor/plugins/pastefromgdocs/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [];
  }

}
