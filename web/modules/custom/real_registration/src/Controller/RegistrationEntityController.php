<?php

namespace Drupal\real_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses.
 */
class RegistrationEntityController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The Route Match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a RegistrationEntityController object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match')
    );
  }

  /**
   * Returns a participant confirmation page template.
   */
  public function confirmation() {
    return [
      '#theme' => 'registration_confirmation',
      '#message' => [
        'title' => $this->t('Thank you!'),
        'body' => $this->t('Your request has been sent.'),
      ],
      '#link' => [
        'url' => Url::fromRoute('page_manager.page_view_events_events-layout_builder-default'),
        'label' => $this->t('Back to events'),
      ],
    ];
  }

  /**
   * Route title callback.
   */
  public function title() {
    return $this->t('Participant confirmation');
  }

}
