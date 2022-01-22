<?php

namespace Drupal\nunavut_core\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Nunavut: Core routes.
 */
class DiscoveryAjaxSlider extends ControllerBase {

  /**
   * The Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The Renderer.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $renderer
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Builds the response.
   *
   * @throws \Exception
   */
  public function build($node) {
    // @todo move $build to mediaHelper
    $build = [];
    $slider = [];
    $selected = [];

    try {
      /** @var \Drupal\media\MediaStorage $mediaStorage */
      $mediaStorage = $this->entityTypeManager->getStorage('media');

      $ids = $mediaStorage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', 'discovery_nunavut')
        ->execute();

      for ($i = 0; $i < 3; $i++) {
        $index = array_keys($ids)[rand(0, count($ids) - 1)];
        $selected[$index] = $ids[$index];

        unset($ids[$index]);
      }

      $medias = $mediaStorage
        ->loadMultiple($selected);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->getLogger('nunavut_core')->error($e->getMessage());

      return $build;
    }

    $view_builder = $this->entityTypeManager->getViewBuilder('media');

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($medias as $media) {
      $slider[] = [
        'media' => $view_builder->view($media, 'default'),
      ];
    }

    $build['slider'] = $slider;

    return new Response(
      $this->renderer->render($build)
    );
  }

}
