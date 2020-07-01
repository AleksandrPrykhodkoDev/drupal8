<?php

namespace Drupal\fj_master\Plugin\Block;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'CaseStudyHeaderBlock' block.
 *
 * @Block(
 *  id = "case_study_header_block",
 *  admin_label = @Translation("Case Study Header Block")
 * )
 */
class CaseStudyHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    if ($node instanceof NodeInterface && $node->getType() == 'case_study') {
      $image_uri = $node->field_case_study_header_img
        ->entity->field_media_image->first()->entity->getFileUri();

      $style = $this->entityTypeManager->getStorage('image_style')
        ->load('small_cover_bg');

      $build = [
        '#theme' => 'case_study_header',
        '#title' => $node->getTitle(),
        '#image_url' => $style->buildUrl($image_uri),
      ];
    }
    else {
      $build = [];
    }

    return $build;
  }

}
