<?php

namespace Drupal\fj_master\Plugin\Block;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ServicePagerBlock' block.
 *
 * @Block(
 *  id = "service_pager_block",
 *  admin_label = @Translation("Service pager block")
 * )
 */
class ServicePagerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRoute;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $current_route, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRoute = $current_route;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->currentRoute->getParameter('node');
    if ($node instanceof NodeInterface && $node->getType() == 'service') {
      $items = $this->loadServicePagerItems($node);
      $build = [
        '#theme' => 'service_pager_items',
        '#next' => $items['next'],
        '#previous' => $items['previous'],
      ];
    }
    else {
      $build = [];
    }

    return $build;
  }

  /**
   * Get Node Entity Query.
   */
  protected function getEntityQuery() {
    return $this->entityTypeManager
      ->getStorage('node')
      ->getQuery('AND');
  }

  /**
   * Generates links to next and previous services (if available).
   *
   * @param \Drupal\node\Entity\Node $node
   *   The current node to use as a reference for pager item determination.
   *
   * @return array
   *   Keyed array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function loadServicePagerItems(Node $node) {
    $items = [];
    $items['next'] = $this->getLink($node->id(), 'next');
    $items['previous'] = $this->getLink($node->id(), 'previous');

    return $items;
  }

  /**
   * Get link based on ID and context.
   *
   * @param int $id
   *   Node ID.
   * @param string $context
   *   Context value (can be 'next' or 'previous')
   *
   * @return \Drupal\Core\Link|null
   *   Link to node
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getLink($id, $context) {
    $item_query = $this->getEntityQuery()
      ->condition('type', 'service');

    if ($context == 'next') {
      $item_query->condition('nid', $id, '>');
      $item_query->sort('nid', 'asc');
    }
    else {
      $item_query->condition('nid', $id, '<');
      $item_query->sort('nid', 'desc');
    }

    $item_query->range(0, 1);
    $item = $item_query->execute();

    if (!empty($item)) {
      $item = array_pop($item);
      $node = $this->entityTypeManager
        ->getStorage('node')
        ->load($item);
      return $node->toLink($node->getTitle());
    }
    else {
      return NULL;
    }
  }

}
