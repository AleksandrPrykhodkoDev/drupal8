<?php

namespace Drupal\nunavut_core;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ParagraphsOperations.
 *
 * Handle Paragraph functionality.
 *
 * @package Drupal\marine_paragraph
 */
class ParagraphsOperations implements ContainerInjectionInterface {

  use StringInflectorTrait;
  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A proxies implementation of AccountInterface.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destination;

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Breadcrumb Manager service.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbManager|object|null
   */
  protected $breadcrumb;

  /**
   * Current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch|object|null
   */
  protected $routeMatch;

  /**
   * The Logger Interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Returns the form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Returns the EntityDisplayRepository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * The MediaHelper service.
   *
   * @var \Drupal\nunavut_core\MediaHelper|object|null
   */
  protected $mediaHelper;

  /**
   * The menu tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The PageHelper service.
   *
   * @var \Drupal\nunavut_core\PageHelper|object|null
   */
  protected $pageHelper;

  /**
   * The Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * ParagraphsOperations constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The global Drupal container.
   */
  public function __construct(ContainerInterface $container) {
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->entityDisplayRepository = $container->get('entity_display.repository');
    $this->currentUser = $container->get('current_user');
    $this->destination = $container->get('redirect.destination');
    $this->breadcrumb = $container->get('breadcrumb');
    $this->routeMatch = $container->get('current_route_match');
    $this->formBuilder = $container->get('form_builder');
    $this->mediaHelper = $container->get('nunavut_core.media_helper');
    $this->menuTree = $container->get('menu.link_tree');
    $this->logger = $this->getLogger('nunavut_core');
    $this->pageHelper = $container->get('nunavut_core.page_helper');
    $this->renderer = $container->get('renderer');

    $this->config = $container
      ->get('config.factory')
      ->get('nunavut_core.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container);
  }

  /**
   * Paragraph preprocess.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocess(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    // Field 'field_enable_fade_animation' is boolean.
    if (
      $paragraph->hasField('field_enable_fade_animation')
      && $paragraph->get('field_enable_fade_animation')->getString()
    ) {
      $animation = [
        'data-aos' => 'fade-up',
        'data-aos-easing' => 'linear',
        'data-aos-duration' => '750',
        'data-aos-anchor-placement' => 'top-center',
      ];

      $variables['attributes'] += $animation;
    }

    $this->invokeInflectorMethods(
      $paragraph,
      __FUNCTION__,
      [&$variables]
    );
  }

  /**
   * Paragraph widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlter(array &$element, FormState &$form_state, array $context) {
    $this->invokeInflectorMethods($element, __FUNCTION__, [
      &$element,
      &$form_state,
      $context,
    ]);
  }

  /**
   * Preprocess paragraph block_reference.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphBlockReference(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];
    $view_mode = 'full';

    if ($paragraph->hasField('field_view_mode')) {
      $view_mode = $paragraph->get('field_view_mode')->getString();
    }

    if ($paragraph->hasField('field_block')) {
      $bid = $paragraph->get('field_block')->getString();
      $view_builder = $this->entityTypeManager->getViewBuilder('block');

      if ($node = $this->entityTypeManager->getStorage('block')->load($bid)) {
        $variables['content']['field_block'][0] = $view_builder->view($node, $view_mode);
        $variables['content']['title_suffix'] = $variables['title_suffix'] ?? NULL;
        hide($variables['content']['field_view_mode']);
        hide($variables['title_suffix']);
      }
    }
  }

  /**
   * Paragraph block_reference widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphBlockReference(array &$element, FormState &$form_state, array $context) {
    if (isset($element['subform']['field_view_mode'])) {
      $element['subform']['field_view_mode']['widget']['#options'] = $this
        ->entityDisplayRepository
        ->getViewModeOptions('block');
    }

    if (isset($element['field_view_mode'])) {
      $element['field_view_mode']['widget']['#options'] = $this
        ->entityDisplayRepository
        ->getViewModeOptions('block');
    }
  }

  /**
   * Preprocess paragraph node_reference.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphNodeReference(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];
    $view_mode = 'default';

    if ($paragraph->hasField('field_view_mode')) {
      $view_mode = $paragraph->get('field_view_mode')->getString();
    }

    if ($paragraph->hasField('field_node')) {
      $nid = $paragraph->get('field_node')->getString();
      $view_builder = $this->entityTypeManager->getViewBuilder('node');

      if ($node = $this->entityTypeManager->getStorage('node')->load($nid)) {
        $variables['content']['field_node'][0] = $view_builder->view($node, $view_mode);
        $variables['content']['title_suffix'] = $variables['title_suffix'] ?? NULL;
        hide($variables['content']['field_view_mode']);
        hide($variables['title_suffix']);
      }
    }
  }

  /**
   * Paragraph widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphNodeReference(array &$element, FormState &$form_state, array $context) {
    if (isset($element['subform']['field_view_mode'])) {
      $element['subform']['field_view_mode']['widget']['#options'] = $this
        ->entityDisplayRepository
        ->getViewModeOptions('node');
    }

    if (isset($element['field_view_mode'])) {
      $element['field_view_mode']['widget']['#options'] = $this
        ->entityDisplayRepository
        ->getViewModeOptions('node');
    }
  }

  /**
   * Preprocess paragraph button.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphButton(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    $url = '';
    $title = '';

    if (
      $paragraph->hasField('field_link')
      && !$paragraph->get('field_link')->isEmpty()
    ) {
      try {
        /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link */
        if ($link = $paragraph->get('field_link')->first()) {
          $url = $link->getUrl()->toString();
          $title = $link->get('title')->getString();
        }
      }
      catch (MissingDataException $e) {
        $url = NULL;
        $this->logger->alert(
          'Paragraph @id has missing url or title',
          ['@id' => $paragraph->id()]
              );
      }
    }

    if (
      $paragraph->hasField('field_image')
      /** @var \Drupal\media\Entity\Media $icon */
      && ($icon = $paragraph->get('field_image')->entity)
    ) {
      $variables['icon'] = [
        '#type' => 'inline_template',
        '#template' => '<img src="{{ icon }}"/>',
        '#context' => ['icon' => $this->mediaHelper->getImageUrl($icon)['url']],
      ];
    }

    $variables['link']['target'] =
      $paragraph->get('field_target')->getString() ?: '_self';

    $variables['link']['url'] = $url;
    $variables['link']['title'] = $title;

    $variables['#attached']['library'][] = 'core/drupal.dialog.ajax';
  }

  /**
   * Paragraph button widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphButton(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for button paragraph.
  }

  /**
   * Preprocess paragraph card.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphCard(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    if (
      $paragraph->hasField('field_card_type')
      && $paragraph->hasField('field_card')
      && !$paragraph->get('field_card')->isEmpty()
      /** @var \Drupal\node\Entity\Node $node */
      && ($node = $paragraph->get('field_card')->entity)
    ) {
      $node_type = $node->getType();
      $card_type = $paragraph->get('field_card_type')->getString();

      if ($node_type === 'page' && $card_type === 'rounded') {
        $variables['content']['field_card'] = $this
          ->pageHelper
          ->prepareRenderRoundedPageCard($node);

        hide($variables['content']['field_card_media']);
        hide($variables['content']['field_width']);
        hide($variables['content']['field_height']);
      }

      if ($node_type === 'page' && $card_type === 'default') {
        $variables['content']['field_card'] = $this
          ->pageHelper
          ->prepareRenderPhotoPage($node);

        hide($variables['content']['field_card_media']);
        hide($variables['content']['field_width']);
        hide($variables['content']['field_height']);
      }
    }
  }

  /**
   * Paragraph card widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphCard(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for card paragraph.
  }

  /**
   * Preprocess paragraph container.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphContainer(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    unset($variables['content']['field_container_type']);

    $tag = $paragraph->hasField('field_container_tag')
      ? $paragraph->get('field_container_tag')->getString()
      : '';

    $bg_media = [];
    if ($paragraph->hasField('field_background_image')) {
      /** @var \Drupal\media\Entity\Media $media */
      if ($media = $paragraph->get('field_background_image')->entity) {
        $bg_media['type'] = $media->bundle();
        $bg_media['source'] = $this->mediaHelper->loadBackground($media);
      }
    }

    $classes = [];
    $fields = [
      'field_container_type',
      'field_background_color',
      'field_classes',
      'field_border_color',
      'field_border_width',
      'field_additional_classes',
    ];

    foreach ($fields as $field) {
      if ($paragraph->hasField($field)) {
        $class_value = $paragraph->get($field)->getString();
        $class_value = str_replace(',', ' ', $class_value);
        $classes[] = $class_value;
      }
    }

    $this->getParagraphBorders($paragraph, $classes);

    $class = implode(' ', $classes);

    $variables['content']['field_content'] = [
      '#theme' => 'paragraph_container',
      '#container_class' => $class,
      '#tag' => $tag,
      '#background_media' => $bg_media,
      '#content' => $variables['content']['field_content'],
      '#title_suffix' => $variables['title_suffix'],
    ];
  }

  /**
   * Preprocess paragraph container.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphLayoutSection(array &$variables): void {
    $tag = '';
    $bg_media = [];
    $classes = [];

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    if ($paragraph->hasField('field_container_tag')) {
      $tag = $paragraph->get('field_container_tag')->getString();
    }

    if (
      $paragraph->hasField('field_background_image')
      /** @var \Drupal\media\Entity\Media $media */
      && ($media = $paragraph->get('field_background_image')->entity)
    ) {
      $bg_media['type'] = $media->bundle();
      $bg_media['source'] = $this->mediaHelper->loadBackground($media);
    }

    $fields = [
      'field_container_type',
      'field_background_color',
      'field_classes',
      'field_border_color',
      'field_border_width',
      'field_additional_classes',
      'field_opacity',
      'field_padding',
    ];

    foreach ($fields as $field) {
      if ($paragraph->hasField($field)) {
        $class_value = $paragraph->get($field)->getString();
        $class_value = str_replace(',', ' ', $class_value);
        $classes[] = $class_value;
      }
    }

    $this->getParagraphBorders($paragraph, $classes);

    $variables['tag'] = $tag;
    $variables['background_media'] = $bg_media;
    $variables['container_class'] = $classes;
  }

  /**
   * Paragraph container widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphContainer(array &$element, FormState &$form_state, array $context) {
    if (isset($element['subform']['field_container_tag'])) {
      unset($element['subform']['field_container_tag']['widget']['#options']['_none']);
    }

    if (isset($element['field_container_tag'])) {
      unset($element['field_container_tag']['widget']['#options']['_none']);
    }

    $this->processBordersFormSection($element);
  }

  /**
   * Paragraph layout section widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphLayoutSection(
    array &$element,
    FormState &$form_state,
    array $context
  ) {
    if (isset($element['field_container_tag'])) {
      unset($element['field_container_tag']['widget']['#options']['_none']);
    }

    $this->processBordersFormSection($element);
  }

  /**
   * Preprocess paragraph formatted_text.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphFormattedText(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    // $paragraph = $variables['paragraph'];
    // @todo Implement preprocess method for formatted_text paragraph.
  }

  /**
   * Paragraph formatted_text widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphFormattedText(
    array &$element,
    FormState &$form_state,
    array $context
  ) {
    // @todo Implement widgetFormAlter method for formatted_text paragraph.
  }

  /**
   * Preprocess paragraph image.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphImage(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    /** @var \Drupal\media\Entity\Media $media */
    $media = $paragraph->get('field_image')->entity;

    $image_style = $paragraph->get('field_image_styles')->getString();

    $image_modal = $paragraph->hasField('field_show_image_popup')
      ? (bool) $paragraph->get('field_show_image_popup')->getString()
      : FALSE;

    if (
      $paragraph->hasField('field_link')
      && !$paragraph->get('field_link')->isEmpty()
    ) {
      try {
        /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link */
        if ($link = $paragraph->get('field_link')->first()) {
          $variables['image_url'] = $link->getUrl();
        }
      }
      catch (MissingDataException $e) {
        $this->logger->alert(
          'Paragraph @id has missing url or title',
          [
            '@id' => $paragraph->id(),
          ]
        );
      }
    }

    if ($media && $media->hasField('field_media_image')) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get('field_media_image')->entity;

      $image_uri = $file->getFileUri();

      if ($image_modal) {
        $variables['image_modal'] = $image_uri;
      }

      if (!empty($image_style)) {
        /** @var \Drupal\image\Entity\ImageStyle $style */
        try {
          $style = $this->entityTypeManager
            ->getStorage('image_style')
            ->load($image_style);

          $uri = $style->buildUri($image_uri);

          if (!file_exists($uri)) {
            $style->createDerivative($image_uri, $uri);
          }
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
          $this->logger->error('Error with loading image style');
          $uri = $image_uri;
        }
      }
      else {
        $uri = $image_uri;
      }

      $variables['media_uri'] = $uri;
      $variables['absolute_media_uri'] = Url::fromUri(file_create_url($uri))
        ->toString();
    }

    $border_classes = [];
    $this->getParagraphBorders($paragraph, $border_classes);

    $variables['border_classes'] = $border_classes;
  }

  /**
   * Paragraph image widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphImage(
    array &$element,
    FormState &$form_state,
    array $context
  ) {
    $this->processBordersFormSection($element);
  }

  /**
   * Preprocess paragraph image_map.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphImageMap(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    $area_list = [];
    $areas = $paragraph->get('field_links')->getValue();
    foreach ($areas as $key => $area) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $area_item */
      $area_item = $this->entityTypeManager
        ->getStorage('paragraph')
        ->load($area['target_id']);

      if ($area_item) {
        $selector = $area_item->hasField('field_selector')
          ? $area_item->get('field_selector')->getString()
          : '';

        $target = $area_item->hasField('field_target')
          ? $area_item->get('field_target')->getString()
          : '_self';

        $url = '';
        $title = '';

        if ($area_item->hasField('field_unlimited_link')) {
          try {
            /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link */
            if ($link = $area_item->get('field_unlimited_link')->first()) {
              $url = $link->getUrl()->toString();
              $title = $link->get('title')->getString();
            }
          }
          catch (MissingDataException $e) {
            $this->logger->alert(
              'Paragraph @id has missing url or title',
              ['@id' => $paragraph->id()]
            );
          }
        }

        $area_list[$key] = [
          'selector' => $selector,
          'url' => $url,
          'title' => $title,
          'target' => $target,
        ];
      }
    }

    /** @var \Drupal\media\Entity\Media $media */
    if ($media = $paragraph->get('field_image')->entity) {
      $variables['content']['field_image'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="map-image {{top_position}}">{{ value | raw }}</div>',
        '#context' => [
          'value' => $this->mediaHelper->getImageUrl($media)['svg'],
          'list' => $area_list,
        ],
      ];
    }
  }

  /**
   * Paragraph image_map widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphImageMap(
    array &$element,
  FormState &$form_state,
  array $context) {
    // @todo Implement widgetFormAlter method for image_map paragraph.
  }

  /**
   * Paragraph map widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphMap(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for map paragraph.
  }

  /**
   * Preprocess paragraph media_slider.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphMediaSlider(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    // $paragraph = $variables['paragraph'];
    // @todo Implement preprocess method for media_slider paragraph.
  }

  /**
   * Paragraph media_slider widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphMediaSlider(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for media_slider paragraph.
  }

  /**
   * Preprocess paragraph menu_reference.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphMenuReference(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];
    $menu_name = $paragraph->get('field_menu')->getString();
    if (!empty($menu_name)) {
      // Build the typical default set of menu tree parameters.
      $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);
      // Load the tree based on this set of parameters.
      $tree = $this->menuTree->load($menu_name, $parameters);
      // Transform the tree using the manipulators you want.
      $manipulators = [
        // Only show links that are accessible for the current user.
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        // Use the default sorting of menu links.
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ];
      $tree = $this->menuTree->transform($tree, $manipulators);
      // Finally, build a renderable array from the transformed tree.
      $menu = $this->menuTree->build($tree);
      $variables['content'] = $menu;
    }

  }

  /**
   * Paragraph menu_reference widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphMenuReference(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for menu_reference paragraph.
  }

  /**
   * Preprocess paragraph space.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphSpace(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    if ($paragraph->hasField('field_space')) {
      $variables['space'] = $paragraph->get('field_space')->getString();
    }
  }

  /**
   * Paragraph space widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphSpace(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for space paragraph.
  }

  /**
   * Preprocess paragraph video.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphVideo(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];
    /** @var \Drupal\media\Entity\Media $media */
    $media = $paragraph->get('field_video')->entity;
    $variables['media_title'] = $media->getName();
  }

  /**
   * Paragraph video widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphVideo(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for video paragraph.
  }

  /**
   * Preprocess paragraph views_reference.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphViewsReference(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    // $paragraph = $variables['paragraph'];
    // @todo Implement preprocess method for views_reference paragraph.
  }

  /**
   * Paragraph views_reference widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   * @param \Drupal\Core\Form\FormState $form_state
   *   The current state of the form.
   * @param array $context
   *   An associative array. See hook_field_widget_form_alter() for the
   *   structure and content of the array.
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  public function widgetFormAlterParagraphViewsReference(array &$element, FormState &$form_state, array $context) {
    // @todo Implement widgetFormAlter method for views_reference paragraph.
  }

  /**
   * Preprocess paragraph views_reference.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphLeftBorder(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];

    $this->pageHelper->defaultVariables($variables);

    $variables['left_text'] = $paragraph
      ->get('field_bottom_left_text')
      ->getString();
  }

  /**
   * Preprocess paragraph views_reference.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphWeather(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];
    $tooltip = [];

    /** @var \Drupal\node\Entity\Node $node */
    if ($node = $this->routeMatch->getParameter('node')) {
      $tooltip = [
        '#type' => 'inline_template',
        '#template' => '<h2 class="color-red">{{ title }}</h2>',
        '#context' => [
          'title' => $node->label(),
        ],
      ];
    }

    $config = $this->config->get('mapbox_gl_settings');

    $point = [
      'lat' => $config['lat'],
      'lng' => $config['lng'],
    ];

    if (
      $paragraph->hasField('field_location')
      && !$paragraph->get('field_location')->isEmpty()
    ) {
      $point = $paragraph
        ->get('field_location')
        ->first()
        ->getValue();
    }

    $settings = [
      'access_token' => $config['access_token'],
      'map_settings' => [
        'container' => 'weather-map',
        'style' => $config['style'],
        'zoom' => 4,
        'center' => [
          $point['lng'],
          $point['lat'],
        ],
      ],
      'point' => [
        'lat' => $point['lat'],
        'lng' => $point['lng'],
        'tooltip' => $this->renderer->render($tooltip),
      ],
    ];

    $variables['content']['nunavut_map'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="nunavut-map"><div id="weather-map"></div></div>',
    ];

    hide($variables['content']['field_location']);

    $variables['#attached']['drupalSettings']['mapboxGl'] = $settings;
    $variables['#attached']['library'][] = 'nunavut_core/libraries.mapbox-gl-js';
    $variables['#attached']['library'][] = 'nunavut_core/paragraph_weather.mapbox_gl';
  }

  /**
   * Preprocess paragraph map.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @see hook_preprocess_paragraph()
   */
  public function preprocessParagraphMap(array &$variables): void {
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $variables['paragraph'];
    $tooltip = [];

    /** @var \Drupal\node\Entity\Node $node */
    if ($node = $this->routeMatch->getParameter('node')) {
      $tooltip = [
        '#type' => 'inline_template',
        '#template' => '<h2 class="color-red">{{ title }}</h2>',
        '#context' => [
          'title' => $node->label(),
        ],
      ];
    }

    $config = $this->config->get('mapbox_gl_settings');

    $point = [
      'lat' => $config['lat'],
      'lng' => $config['lng'],
    ];

    if (
      $paragraph->hasField('field_location')
      && !$paragraph->get('field_location')->isEmpty()
    ) {
      $point = $paragraph
        ->get('field_location')
        ->first()
        ->getValue();
    }

    $settings = [
      'access_token' => $config['access_token'],
      'map_settings' => [
        'container' => 'weather-map',
        'style' => $config['style'],
        'zoom' => 4,
        'center' => [
          $point['lng'],
          $point['lat'],
        ],
      ],
      'point' => [
        'lat' => $point['lat'],
        'lng' => $point['lng'],
        'tooltip' => $tooltip,
      ],
    ];

    $variables['content']['nunavut_map'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="nunavut-map" style="position:relative"><div id="weather-map" style="min-height:400px"></div></div>',
    ];

    hide($variables['content']['field_location']);

    $variables['#attached']['drupalSettings']['mapboxGl'] = $settings;
    $variables['#attached']['library'][] = 'nunavut_core/libraries.mapbox-gl-js';
    $variables['#attached']['library'][] = 'nunavut_core/paragraph_weather.mapbox_gl';
  }

  /**
   * Builds drupal selector of element.
   *
   * @param array $element
   *   Element array.
   *
   * @return string
   *   Drupal selector of element.
   */
  private function buildDrupalSelector(array $element): string {
    $widget = $element['field_use_default_page_border']['widget'] ?? [];

    $element_id = sprintf(
      'edit-%s-%s-value',
      implode('-', $widget['#field_parents'] ?? []),
      $widget['#field_name'] ?? NULL,
    );

    return str_replace('_', '-', $element_id);
  }

  /**
   * Loads borders from paragraph.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   Paragraph.
   * @param array $classes
   *   Classes array.
   */
  private function getParagraphBorders(Paragraph $paragraph, array &$classes): void {
    if (
      $paragraph->hasField('field_use_default_page_border')
      && $paragraph->get('field_use_default_page_border')->getString()
    ) {
      $vars = [];
      $this->pageHelper->defaultVariables($vars);
      $classes[] = 'nunavut-border-left';
      $classes[] = 'border-width-default';
      $classes[] = $vars['page_header_border'];
    }
    else {
      $fields = [
        'field_border_bottom' => 'nunavut-border-bottom',
        'field_border_right' => 'nunavut-border-right',
        'field_border_left' => 'nunavut-border-left',
        'field_border_top' => 'nunavut-border-top',
        'field_border_color' => NULL,
        'field_border_width' => NULL,
      ];

      foreach ($fields as $field => $style_class) {
        if ($paragraph->hasField($field)) {
          if ($style_class && $paragraph->get($field)->getString()) {
            $classes[] = $style_class;
          }
          else {
            $classes[] = $paragraph->get($field)->getString();
          }
        }
      }
    }
  }

  /**
   * Process border section for Paragraph widget form alter.
   *
   * @param array $element
   *   The field widget form element as constructed by
   *   \Drupal\Core\Field\WidgetBaseInterface::form().
   *
   * @see hook_field_widget_WIDGET_TYPE_form_alter()
   */
  private function processBordersFormSection(array &$element) {
    if (isset($element['field_use_default_page_border'])) {
      $element_id = $this->buildDrupalSelector($element);

      $fields = [
        'field_border_bottom',
        'field_border_color',
        'field_border_left',
        'field_border_right',
        'field_border_top',
        'field_border_width',
      ];

      foreach ($fields as $field) {
        $element[$field]['#states'] = [
          'invisible' => [
            ':input[data-drupal-selector="' . $element_id . '"]' => ['checked' => TRUE],
          ],
        ];
      }
    }
  }

}
