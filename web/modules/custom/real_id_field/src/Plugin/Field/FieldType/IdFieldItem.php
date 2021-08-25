<?php

namespace Drupal\real_id_field\Plugin\Field\FieldType;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'id_field' field type.
 *
 * @FieldType(
 *   id = "id_field",
 *   label = @Translation("ID field"),
 *   category = @Translation("Real"),
 *   default_widget = "id_field_default",
 *   default_formatter = "id_field"
 * )
 */
class IdFieldItem extends StringItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'max_length' => 20,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // This field can't be empty, even if there is no value still need to pass
    // through preSave method to generate the ID.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('ID value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    if (empty($this->values['value'])) {
      $this->value = $this->generateId();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['value'] = (new self($field_definition->getItemDefinition(), $field_definition->getName()))->generateId();
    return $values;
  }

  /**
   * Genarate the id.
   *
   * Pattern: [YYY year]-[number of node of this type + 1].
   *
   * @return string
   *   the id generated.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Plugin definition exception.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Plugin not found exception.
   */
  public function generateId() {
    if (!empty($this->getParent())) {
      $entity = $this->getEntity();
      $entity_type = $entity->getEntityTypeId();
      $entity_bundle = $entity->bundle();
    }
    else {
      $definition = $this->getFieldDefinition();
      $entity_type = $definition->getTargetEntityTypeId();
      $entity_bundle = $definition->getTargetBundle();
    }
    $count = $this->countEntities($entity_type, $entity_bundle) + 1;
    return "{$this->getYear()}-{$count}";
  }

  /**
   * Get current year.
   *
   * @return string
   *   The current year.
   */
  protected function getYear() {
    $date = new DrupalDateTime();
    return \Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'Y');
  }

  /**
   * Get number of entities of specific type and bundle.
   *
   * @param string $type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   *
   * @return array|int
   *   The number of available entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function countEntities($type, $bundle) {
    $storage = \Drupal::entityTypeManager()->getStorage($type);
    $query = $storage->getQuery()
      ->condition('type', $bundle);
    return $query->count()->execute();
  }

}
