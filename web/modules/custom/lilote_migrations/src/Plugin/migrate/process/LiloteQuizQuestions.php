<?php

namespace Drupal\lilote_migrations\Plugin\migrate\process;

use Drupal\migrate_plus\Plugin\migrate\process\EntityGenerate;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Processes quiz questions.
 *
 * @code
 * process:
 *   question:
 *     plugin: lilote_migrations_quiz_questions
 *     method: process
 *     source:
 *       - question_number
 *       - question
 *       - correct_answer
 *       - wrong_answer_1
 *       - wrong_answer_2
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "lilote_migrations_quiz_questions",
 *   handle_multiples = TRUE
 * )
 */
class LiloteQuizQuestions extends EntityGenerate {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    if (!is_array($value) || count($value) != 5) {
      return;
    }

    $feed_question = $value[1];
    $feed_correct_answer = $value[2];
    $feed_wrong_answers = [
      $value[3],
      $value[4],
    ];

    $node_storage = $this->entityTypeManager->getStorage('node');
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    $to_be_created = TRUE;
    $paragraphs_ids = [];
    // Search for existing quiz node.
    $source_title = $row->getDestinationProperty('title');
    $ids = $node_storage->getQuery()
      ->condition('title', $source_title)
      ->condition('type', 'quiz')
      ->execute();
    // Proceed, if there is node, because otherwise we just create new question.
    if (!empty($ids)) {
      $node_id = reset($ids);
      $node = $node_storage->load($node_id);
      // If question field is not empty, check for updates.
      if (!$node->question->isEmpty()) {
        $paragraphs = $node->question->getValue();
        foreach ($paragraphs as $element) {
          $paragraph = $paragraph_storage->load($element['target_id']);
          $question = $paragraph->question->value;
          $to_be_updated = FALSE;
          // If match by question is found, check for updates for other fields.
          if ($question == $feed_question) {
            $to_be_created = FALSE;
            if (!$paragraph->correct_answer->isEmpty() && $paragraph->correct_answer->value != $feed_correct_answer) {
              $to_be_updated = TRUE;
              $paragraph->correct_answer->setValue($feed_correct_answer);
            }
            if (!$paragraph->wrong_answer->isEmpty()) {
              foreach ($paragraph->wrong_answer->getValue() as $delta => $wrong_answer_item) {
                if (!empty($feed_wrong_answers[$delta]) && $wrong_answer_item['value'] != $feed_wrong_answers[$delta]) {
                  $to_be_updated = TRUE;
                  $paragraph->wrong_answer->set($delta, $feed_wrong_answers[$delta]);
                }
              }
            }
          }
          // If answers fields are updated, need to save paragraph.
          if ($to_be_updated) {
            $paragraph->save();
          }
          // Add IDs for each paragraph, because we are expected
          // to return whole set of values for the destination field.
          $paragraphs_ids[] = [
            'target_id' => $paragraph->id(),
            'target_revision_id' => $paragraph->getRevisionId(),
          ];
        }
      }
    }
    // If there is no existing question, create new one.
    if ($to_be_created) {
      $paragraph_question = $paragraph_storage->create([
        'type' => 'question',
        'question' => $feed_question,
        'correct_answer' => $feed_correct_answer,
        'wrong_answer' => [
          $feed_wrong_answers[0],
          $feed_wrong_answers[1],
        ],
      ]);
      $paragraph_question->save();
      $paragraphs_ids[] = [
        'target_id' => $paragraph_question->id(),
        'target_revision_id' => $paragraph_question->getRevisionId(),
      ];
    }

    return $paragraphs_ids;
  }

}
