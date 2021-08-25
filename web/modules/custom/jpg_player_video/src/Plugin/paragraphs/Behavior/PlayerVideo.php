<?php

namespace Drupal\jpg_player_video\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphsBehaviorBase;


/**
 * Enables player video functionalities.
 *
 * @ParagraphsBehavior(
 *   id = "jpg_player_video",
 *   label = @Translation("JPG - Player Video"),
 *   description = @Translation("Enables player video functionalities."),
 *   weight = 0
 * )
 */
class PlayerVideo extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function view(
    array &$build,
    Paragraph $paragraph,
    EntityViewDisplayInterface $display,
    $view_mode
  ) {
    if (!$paragraph->field_main_video->isEmpty() &&
      !$paragraph->field_more_videos->isEmpty()) {
      if (!isset($build['#attributes']['library'])) {
        $build['#attached']['library'] = ['jpg_player_video/player_video'];
      }
      else {
        $build['#attached']['library'][] = 'jpg_player_video/player_video';
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('paragraph', $paragraphs_type->id());
    return array_key_exists('field_more_videos', $field_definitions) &&
      array_key_exists('field_main_video', $field_definitions);
  }

}
