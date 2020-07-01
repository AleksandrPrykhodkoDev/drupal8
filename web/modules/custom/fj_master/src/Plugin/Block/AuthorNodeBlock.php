<?php

namespace Drupal\fj_master\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'AuthorNodeBlock' block.
 *
 * @Block(
 *  id = "author_node_block",
 *  admin_label = @Translation("Author of node block"),
 * )
 */
class AuthorNodeBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $authorPicture = NULL;
    // TODO: Replace currentRoute by block context.
    $node = $this->currentRoute->getParameter('node');
    if (!$node) {
      return [];
    }
    if (!$user = $node->uid->entity) {
      return [];
    }
    if (!$user->user_picture->isEmpty()) {
      $authorPicture = file_create_url($user->user_picture->entity->getFileUri());
    }

    $build = [
      '#theme' => 'author_node',
      '#author' => $user->field_full_name->value,
      '#author_picture' => $authorPicture,
      '#summary' => $user->field_summary->value,
    ];

    return $build;
  }

}
