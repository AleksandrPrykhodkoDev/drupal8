<?php

namespace Drupal\nunavut_core\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class NunavutRedirects.
 *
 * Nunavut Core Redirects.
 *
 * @package Drupal\marine_news\EventSubscriber
 */
class NunavutRedirects implements EventSubscriberInterface {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * MarineSearchRedirects constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The ConfigFactoryInterface instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Check for redirection.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The GetResponseEvent instance.
   */
  public function checkForRedirection(RequestEvent $event) {
    $search_page_path = Url::fromRoute('view.search.search_page')
      ->toString();

    $path = $event->getRequest()->getRequestUri();

    if (strpos($path, $search_page_path) === 0) {
      $query = $event->getRequest()->query->all();
      $query = ['query' => $query];

      $url = Url::fromRoute('nunavut_core.search', [], $query)
        ->toString();

      $event->setResponse(
        new RedirectResponse($url, 301, [])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkForRedirection'];

    return $events;
  }

}
