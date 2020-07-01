<?php

namespace Drupal\ss_common\Plugin\Layout;

use Drupal\layout_plugin\Plugin\Layout\LayoutBase;

/**
 * The plugin that handles the main front template.
 *
 * @ingroup layout_template_plugins
 *
 * @Layout(
 *   id = "page_layout_main_front",
 *   label = @Translation("Main Front"),
 *   category = @Translation("Incisive Media"),
 *   description = @Translation("Main Front Layout"),
 *   type = "page",
 *   help = @Translation("Layout"),
 *   template = "page-layout-main-front",
 *   regions = {
 *     "top" = {
 *       "label" = @Translation("Top content region"),
 *       "plugin_id" = "default"
 *     },
 *     "main" = {
 *       "label" = @Translation("Main content region"),
 *       "plugin_id" = "default"
 *     }
 *   }
 * )
 */
class Front extends LayoutBase {}
