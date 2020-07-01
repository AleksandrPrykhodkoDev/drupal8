<?php

namespace Drupal\custom_block_reference\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\LazyContextRepository;
use Drupal\custom_block_reference\Utility\UntranslatedString;

/**
 * Plugin that displays list of available custom blocks in select list.
 *
 * @FieldWidget(
 *   id = "custom_block_reference_widget",
 *   module = "custom_block_reference",
 *   label = @Translation("Custom Block Reference"),
 *   field_types = {
 *     "custom_block_reference_field"
 *   }
 * )
 */
class CustomBlockReferenceWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  use UntranslatedString;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\LazyContextRepository
   */
  protected $contextRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, BlockManagerInterface $block_manager, LazyContextRepository $context_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->blockManager = $block_manager;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.block'),
      $container->get('context.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_settings = $this->getFieldSettings();
    $definitions = $this->blockManager->getDefinitionsForContexts($this->contextRepository->getAvailableContexts());
    $definitions = $this->blockManager->getSortedDefinitions($definitions);

    $value = isset($items[$delta]->value) ? $items[$delta]->value : '';
    $element += [
      '#type' => 'select',
      '#default_value' => $value,
      '#options' => $this->getBlocksByCategories($definitions, $this->filterCategoriesValue($field_settings['block_category'])),
      '#element_validate' => array(
        array($this, 'validate'),
      ),
    ];
    return array('value' => $element);
  }

  /**
   * Returns blocks for categories.
   *
   * @param array $definitions
   *   List of block plugin definitions.
   * @param array $categories
   *   List of categories to filter by.
   *
   * @return array
   *   Array of blocks keyed by plugin_id and label as value.
   */
  public function getBlocksByCategories(array $definitions, array $categories) {
    $blocks = [];
    foreach ($definitions as $plugin_id => $plugin_definition) {
      $category = $this->getUntranslatedString($plugin_definition['category']);
      if (in_array($category, $categories)) {
        $blocks[$plugin_id] = $plugin_definition['admin_label'];
      }
    }

    return !empty($blocks) ? $blocks : ['none' => t('- None -')];
  }

  /**
   * Remove empty values from configuration.
   *
   * @param array $block_categories
   *   Field configuration.
   *
   * @return array
   *   Filtered array of values.
   */
  public function filterCategoriesValue(array $block_categories) {
    // @codingStandardsIgnoreStart
    return array_filter($block_categories, function($value) {
      return !empty($value);
    });
    // @codingStandardsIgnoreEnd
  }

}
