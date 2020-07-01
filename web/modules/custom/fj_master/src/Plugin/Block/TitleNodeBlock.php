<?php

namespace Drupal\fj_master\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'TitleNodeBlock' block.
 *
 * @Block(
 *  id = "title_node_block",
 *  admin_label = @Translation("Title of node block"),
 * )
 */
class TitleNodeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRoute;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $current_route) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRoute = $current_route;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // TODO: Replace currentRoute by block context.
    $node = $this->currentRoute->getParameter('node');
    if (!$node) {
      return [];
    }
    if (!$user = $node->uid->entity) {
      return [];
    }
    $createdDate = date('M d, Y', $node->created->value);

    $build = [
      '#theme' => 'title_node',
      '#title' => $node->getTitle(),
      '#author' => $user->field_full_name->value,
      '#created_date' => $createdDate,
      '#category' => $node->field_blog_category && !$node->field_blog_category->isEmpty() ? $node
        ->field_blog_category->entity->getName() : NULL,
    ];

    return $build;
  }

}
