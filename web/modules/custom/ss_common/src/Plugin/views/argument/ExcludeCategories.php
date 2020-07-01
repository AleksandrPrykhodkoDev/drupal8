<?php

namespace Drupal\ss_common\Plugin\views\argument;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Drupal\taxonomy\VocabularyStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Database;

/**
 * Argument handler to accept a node id.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("exclude_categories")
 */
class ExcludeCategories extends NumericArgument implements ContainerFactoryPluginInterface {

  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\VocabularyStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * Constructs the SimilarTermsArgument object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Connection $connection
   *   The datbase connection.
   * @param VocabularyStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection, VocabularyStorageInterface $vocabulary_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->vocabularyStorage = $vocabulary_storage;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition, $container->get('database'), $container->get('entity.manager')->getStorage('taxonomy_vocabulary')
    );
  }

  /**
   * Define default values for options.
   */
  protected function defineOptions() {
    return parent::defineOptions();
  }

  /**
   * Build options settings form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Add filter(s).
   */
  public function query() {
    $args = $this->view->args;
    $tables = $this->query->tables;
    if (!empty($args)) {
      $categories = Database::getConnection()
        ->select('media__field_categories', 'fc')
        ->fields('fc', ['field_categories_target_id'])
        ->distinct()
        ->execute()
        ->fetchAllKeyed(0, 0);

      if (!isset($tables['taxonomy_term_field_data']['taxonomy_term_field_data'])) {
        $this->query->addTable('taxonomy_term_field_data', NULL, NULL, 'taxonomy_term_field_data');
      }
      $this->query->addWhere(NULL, 'taxonomy_term_field_data.tid', $categories, 'IN');
      if (!isset($tables['taxonomy_term__field_departments']['taxonomy_term__field_departments'])) {
        $this->query->addTable('taxonomy_term__field_departments', NULL, NULL, 'taxonomy_term__field_departments');
        $this->query->addWhere(NULL, 'taxonomy_term__field_departments.field_departments_target_id', $args, 'IN');
      }
    }
  }

}

