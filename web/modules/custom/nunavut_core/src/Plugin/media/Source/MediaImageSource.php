<?php

namespace Drupal\nunavut_core\Plugin\media\Source;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;

/**
 * External Media entity media source.
 *
 * @see \Drupal\file\FileInterface
 *
 * @MediaSource(
 *   id = "image_reference",
 *   label = @Translation("Media Image"),
 *   description = @Translation("Media Image entity."),
 *   allowed_field_types = {"entity_reference"},
 *   thumbnail_alt_metadata_attribute = "alt",
 *   default_thumbnail_filename = "no-thumbnail.png"
 * )
 */
class MediaImageSource extends MediaSourceBase {

  /**
   * Key for "Title" metadata attribute.
   *
   * @var string
   */
  const METADATA_ATTRIBUTE_TITLE = 'title';

  /**
   * Key for "Alternative text" metadata attribute.
   *
   * @var string
   */
  const METADATA_ATTRIBUTE_ALT_TEXT = 'alt_text';

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      static::METADATA_ATTRIBUTE_TITLE => $this->t('Title'),
      static::METADATA_ATTRIBUTE_ALT_TEXT => $this->t('Alternative text'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    switch ($attribute_name) {
      case 'default_name':
        return 'media:' . $media->bundle() . ':' . $media->uuid();

      case 'thumbnail_uri':
        $default_thumbnail_filename = $this
          ->pluginDefinition['default_thumbnail_filename'];

        $default_thumbnail = $this
          ->configFactory
          ->get('media.settings')
          ->get('icon_base_uri') . '/' . $default_thumbnail_filename;

        return $this->getThumbnail($media) ?? $default_thumbnail;

      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }

  /**
   * Gets the thumbnail image URI based on a file entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The Media entity.
   *
   * @return string
   *   File URI of the thumbnail image or NULL if there is no specific icon.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getThumbnail(MediaInterface $media) {
    /** @var \Drupal\media\Entity\Media $source_media */
    $source_media = $media
      ->get($this->configuration['source_field'])
      ->entity;

    /** @var int|string $thumbnail_target */
    $thumbnail_target = $source_media
      ->get('thumbnail')
      ->target_id;

    /** @var \Drupal\file\FileInterface $thumbnail */
    $thumbnail = $this
      ->entityTypeManager
      ->getStorage('file')
      ->load($thumbnail_target);

    return $thumbnail->getFileUri();
  }

  /**
   * Get the source field options for the media type form.
   *
   * This returns all fields related to media entities, filtered by the allowed
   * field types in the media source annotation.
   *
   * @return string[]
   *   A list of source field options for the media type form.
   */
  protected function getSourceFieldOptions() {
    // If there are existing fields to choose from, allow the user to reuse one.
    $options = [];

    $fields = $this
      ->entityFieldManager
      ->getFieldStorageDefinitions('media');

    foreach ($fields as $field_name => $field) {
      $allowed_type = in_array(
        $field->getType(),
        $this->pluginDefinition['allowed_field_types'],
        TRUE
      );

      if ($allowed_type && !$field->isBaseField()) {
        $options[$field_name] = $field->getLabel();
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this->getSourceFieldOptions();

    $form['source_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field with source information'),
      '#default_value' => $this->configuration['source_field'],
      '#empty_option' => $this->t('- Create -'),
      '#options' => $options,
      '#description' => $this->t('Select the field that will store essential information about the media item. If "Create" is selected a new field will be automatically created.'),
    ];

    if (!$options && $form_state->get('operation') === 'add') {
      $form['source_field']['#access'] = FALSE;

      $field_definition = $this
        ->fieldTypeManager
        ->getDefinition(
          reset(
            $this->pluginDefinition['allowed_field_types']
          )
        );

      $form['source_field_message'] = [
        '#markup' => $this->t('%field_type field will be automatically created on this type to store the essential information about the media item.', [
          '%field_type' => $field_definition['label'],
        ]),
      ];
    }
    elseif ($form_state->get('operation') === 'edit') {
      $form['source_field']['#access'] = FALSE;

      $fields = $this
        ->entityFieldManager
        ->getFieldDefinitions(
          'media',
          $form_state->get('type')->id()
        );

      $form['source_field_message'] = [
        '#markup' => $this->t('%field_name field is used to store the essential information about the media item.', [
          '%field_name' => $fields[$this->configuration['source_field']]->getLabel(),
        ]),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    foreach (
      array_intersect_key(
        $form_state->getValues(),
        $this->configuration
      ) as $config_key => $config_value
    ) {
      $this->configuration[$config_key] = $config_value;
    }

    // If no source field is explicitly set, create it now.
    if (empty($this->configuration['source_field'])) {
      $field_storage = $this->createSourceFieldStorage();
      $field_storage->save();

      $this->configuration['source_field'] = $field_storage->getName();
    }
  }

  /**
   * Creates the source field storage definition.
   *
   * By default, the first field type listed in the plugin definition's
   * allowed_field_types array will be the generated field's type.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\field\FieldStorageConfigInterface
   *   The unsaved field storage definition.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createSourceFieldStorage() {
    return $this
      ->entityTypeManager
      ->getStorage('field_storage_config')
      ->create([
        'entity_type' => 'media',
        'field_name' => $this->getSourceFieldName(),
        'type' => reset(
          $this->pluginDefinition['allowed_field_types']
        ),
        'settings' => [
          'target_type' => 'media',
        ],
      ]);
  }

  /**
   * Returns the source field storage definition.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface|null
   *   The field storage definition or NULL if it doesn't exists.
   */
  protected function getSourceFieldStorage() {
    // Nothing to do if no source field is configured yet.
    if ($field = $this->configuration['source_field']) {
      // Even if we do know the name of the source field, there's no
      // guarantee that it exists.
      $fields = $this
        ->entityFieldManager
        ->getFieldStorageDefinitions('media');

      return isset($fields[$field]) ? $fields[$field] : NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldDefinition(MediaTypeInterface $type) {
    // Nothing to do if no source field is configured yet.
    if ($field = $this->configuration['source_field']) {
      // Even if we do know the name of the source field, there is no
      // guarantee that it already exists.
      $fields = $this
        ->entityFieldManager
        ->getFieldDefinitions('media', $type->id());

      return isset($fields[$field]) ? $fields[$field] : NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    $storage = $this->getSourceFieldStorage()
      ?: $this->createSourceFieldStorage();

    return $this
      ->entityTypeManager
      ->getStorage('field_config')
      ->create([
        'field_storage' => $storage,
        'bundle' => $type->id(),
        'label' => $this->pluginDefinition['label'],
        'required' => TRUE,
      ]);
  }

  /**
   * Determine the name of the source field.
   *
   * @return string
   *   The source field name. If one is already stored in configuration, it is
   *   returned. Otherwise, a new, unused one is generated.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSourceFieldName() {
    // Some media sources are using a deriver, so their plugin IDs may contain
    // a separator (usually ':') which is not allowed in field names.
    $base_id = 'field_media_' . str_replace(
      static::DERIVATIVE_SEPARATOR,
      '_',
      $this->getPluginId()
    );

    $tries = 0;

    $storage = $this
      ->entityTypeManager
      ->getStorage('field_storage_config');

    // Iterate at least once, until no field with the generated ID is found.
    do {
      $id = $base_id;

      // If we've tried before, increment and append the suffix.
      if ($tries) {
        $id .= '_' . $tries;
      }

      $field = $storage->load('media.' . $id);

      $tries++;
    } while ($field);

    return $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldValue(MediaInterface $media) {
    $source_field = $this->configuration['source_field'];

    if (empty($source_field)) {
      throw new \RuntimeException(
        'Source field for media source is not defined.'
      );
    }

    /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
    $field_item = $media->get($source_field)->first();

    return $field_item->{$field_item->mainPropertyName()};
  }

  /**
   * {@inheritdoc}
   */
  public function prepareViewDisplay(MediaTypeInterface $type, EntityViewDisplayInterface $display) {
    $display->setComponent(
      $this->getSourceFieldDefinition($type)->getName(),
      [
        'label' => 'visually_hidden',
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormDisplay(MediaTypeInterface $type, EntityFormDisplayInterface $display) {
    // Make sure the source field is placed just after the "name" basefield.
    $name_component = $display->getComponent('name');

    $source_field_weight = isset($name_component['weight'])
      ? $name_component['weight'] + 5
      : -50;

    $display->setComponent(
      $this->getSourceFieldDefinition($type)->getName(),
      [
        'weight' => $source_field_weight,
      ]
    );
  }

}
