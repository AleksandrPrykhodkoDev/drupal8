<?php

namespace Drupal\jpg_player_video\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\DataCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class MediaAjaxRerenderController extends ControllerBase {

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * MediaAjaxRerenderController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RendererInterface $renderer) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Controller callback.
   */
  public function rerender(Request $request) {
    $response = new AjaxResponse();

    $media_id = $request->get('media_id');
    $view_mode = $request->get('view_mode');

    if (!$media_id || !$view_mode) {
      $response->addCommand(new AlertCommand(t('Something went wrong. ')));
      return $response;
    }

    $media_selector = "[data-media-id='$media_id']";

    $media = $this->entityTypeManager->getStorage('media')->load($media_id);
    /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
    $view_builder = $this->entityTypeManager->getViewBuilder('media');

    $output = $view_builder->view($media, $view_mode);
    $html = $this->renderer->renderRoot($output);

    $wrapper_selector = $request->get('wrapper_selector');
    $parent_wrapper_selector = $request->get('parent_wrapper_selector');
    $response->addCommand(new DataCommand($media_selector, 'rerender', [
      'wrapperSelector' => $wrapper_selector,
      'parentWrapperSelector' => $parent_wrapper_selector,
      'html' => $html,
    ]));

    return $response;
  }

}
