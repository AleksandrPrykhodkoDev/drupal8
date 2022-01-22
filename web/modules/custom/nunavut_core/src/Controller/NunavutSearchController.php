<?php

namespace Drupal\nunavut_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NunavutSearchController.
 *
 * Builds search page.
 */
class NunavutSearchController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): NunavutSearchController {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');

    return $instance;
  }

  /**
   * Controller for 'search' page filter.
   *
   * @param string $search
   *   Search string.
   *
   * @return array
   *   Return array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function pageSearch($search): array {
    $view_builder = $this
      ->entityTypeManager
      ->getViewBuilder('node');

    $storage = $this
      ->entityTypeManager
      ->getStorage('node');

    $nid = $this
      ->configFactory
      ->get('nunavut_core.settings')
      ->get('search_page');

    $nid = $nid['page'] ?? 1;
    $nid = $nid == '_none' ? 1 : $nid;

    $node = $storage->load($nid);

    $build = $view_builder->viewField(
      $node->get('field_content'),
      ['label' => 'hidden']
    );

    if (isset($build['#title'])) {
      $build['#title'] = '';
    }

    return $build;
  }

}
