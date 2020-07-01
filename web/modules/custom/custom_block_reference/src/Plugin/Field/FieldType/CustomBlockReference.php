<?php

namespace Drupal\custom_block_reference\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_block_reference\Utility\UntranslatedString;

/**
 * Plugin implementation of the 'custom_block_reference_field' field type.
 *
 * @FieldType(
 *   id = "custom_block_reference_field",
 *   label = @Translation("Custom Block Reference"),
 *   module = "custom_block_reference",
 *   description = @Translation("Field type to create reference to custom block plugin"),
 *   default_widget = "custom_block_reference_widget",
 *   default_formatter = "custom_block_reference_formatter"
 * )
 */
class CustomBlockReference extends FieldItemBase {

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
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $container = \Drupal::getContainer();
    $this->blockManager = $container->get('plugin.manager.block');
    $this->contextRepository = $container->get('context.repository');
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return array(
      'columns' => array(
        'value' => array(
          'type' => 'text',
          'size' => 'small',
          'not null' => FALSE,
        ),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Custom Block Id'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'block_category' => [],
    ) + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element['block_category'] = array(
      '#type' => 'checkboxes',
      '#options' => $this->getAvailableBlockCategories(),
      '#title' => t('Select categories that will be available in select list'),
      '#default_value' => $this->getSetting('block_category'),
      '#weight' => -1,
    );
    return $element;
  }

  /**
   * Returns list of available block categories.
   *
   * @return array
   *   List of available categories.
   */
  public function getAvailableBlockCategories() {
    $categories = [];

    $categories_raw = $this->blockManager->getCategories();
    foreach ($categories_raw as $category) {
      $category = $this->getUntranslatedString($category);
      $categories[$category] = $category;
    }

    return $categories;
  }

}
