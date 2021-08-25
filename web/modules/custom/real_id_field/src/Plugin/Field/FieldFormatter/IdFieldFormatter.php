<?php

namespace Drupal\real_id_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;

/**
 * Plugin implementation of the 'id_field' formatter.
 *
 * @FieldFormatter(
 *   id = "id_field",
 *   label = @Translation("ID field"),
 *   field_types = {
 *     "id_field",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class IdFieldFormatter extends StringFormatter {}
