<?php

namespace Drupal\nunavut_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'menu_reference_render' formatter.
 *
 * @FieldFormatter(
 *   id = "menu_reference_render",
 *   label = @Translation("Rendered menu"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MenuReferenceFormatter extends EntityReferenceFormatterBase {

  /**
   * The active menu trail.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  private MenuLinkTreeInterface $activeTrail;

  /**
   * The menu tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  private MenuLinkTreeInterface $menuLinkTree;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    MenuLinkTreeInterface $menu_link_tree,
    MenuActiveTrail $active_trail
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );

    $this->menuLinkTree = $menu_link_tree;
    $this->activeTrail = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $entity) {
      $menu_name = $entity->get('id');

      // Build the typical default set of menu tree parameters.
      if ($this->getSetting('expand_all_items')) {
        $parameters = new MenuTreeParameters();

        $active_trail = $this
          ->activeTrail
          ->getActiveTrailIds($menu_name);

        $parameters->setActiveTrail($active_trail);
      }
      else {
        $parameters = $this
          ->menuLinkTree
          ->getCurrentRouteMenuTreeParameters($menu_name);
      }

      // Load the tree based on this set of parameters.
      $tree = $this
        ->menuLinkTree
        ->load($menu_name, $parameters);

      $manipulators = [
        ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ];

      $tree = $this
        ->menuLinkTree
        ->transform($tree, $manipulators);

      $elements[] = $this
        ->menuLinkTree
        ->build($tree);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['expand_all_items'] = FALSE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['expand_all_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expand all menu items'),
      '#default_value' => $this->getSetting('expand_all_items'),
      '#description' => $this->t('Override the option found on each menu link used for expanding children and instead display the whole menu tree as expanded.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('expand_all_items')) {
      $summary[] = $this->t('All menu items expanded');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // Limit formatter to only menu entity types.
    return ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'menu');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    // Set 'view label' operation for menu entity.
    // @see \Drupal\system\MenuAccessControlHandler::checkAccess().
    return $entity->access('view label', NULL, TRUE);
  }

}
