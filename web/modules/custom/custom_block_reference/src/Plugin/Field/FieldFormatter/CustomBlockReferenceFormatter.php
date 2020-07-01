<?php

namespace Drupal\custom_block_reference\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Block\BlockManagerInterface;

/**
 * Plugin that renders custom block.
 *
 * @FieldFormatter(
 *   id = "custom_block_reference_formatter",
 *   module = "custom_block_reference",
 *   label = @Translation("Custom Block Formatter"),
 *   field_types = {
 *     "custom_block_reference_field"
 *   }
 * )
 */
class CustomBlockReferenceFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, BlockManagerInterface $block_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->blockManager = $block_manager;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.block')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    static $rendered = FALSE;

    foreach ($items as $delta => $item) {
      if ($item->value == 'views_block:global_search-global_search') {
        if ($rendered) {
          continue;
        }
        $rendered = TRUE;
      }
      $plugin_block = $this->blockManager->createInstance($item->value);
      $elements[$delta] = $plugin_block->build();
    }

    return $elements;
  }

}
