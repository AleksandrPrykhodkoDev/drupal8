<?php

namespace Drupal\nunavut_core\Breadcrumb;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Link;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Class NunavutBreadcrumb.
 *
 * Provides custom breadcrumb for Nunavut Pages.
 *
 * @package Drupal\nunavut_core\Breadcrumb
 */
class NunavutBreadcrumb implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Drupal\Core\Path\CurrentPathStack definition.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * Drupal\Core\Session\AccountInterface definition.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Drupal\language\ConfigurableLanguageManagerInterface definition.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected LanguageManager $languageManager;

  /**
   * The router request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected RequestContext $context;

  /**
   * The menu link access service.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected AccessManagerInterface $accessManager;

  /**
   * The dynamic router service.
   *
   * @var \Symfony\Component\Routing\Matcher\RequestMatcherInterface
   */
  protected RequestMatcherInterface $router;

  /**
   * The inbound path processor.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   */
  protected InboundPathProcessorInterface $pathProcessor;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected TitleResolverInterface $titleResolver;

  /**
   * The patch matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected PathMatcherInterface $pathMatcher;

  /**
   * The patch alias service.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected AliasManager $pathAlias;

  /**
   * Constructs a new NunavutBreadcrumb object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Path\CurrentPathStack $path_current
   *   The current path.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The Language manager service.
   * @param \Drupal\Core\Routing\RequestContext $context
   *   The router request context.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The menu link access service.
   * @param \Symfony\Component\Routing\Matcher\RequestMatcherInterface $router
   *   The dynamic router service.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $path_processor
   *   The inbound path processor.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver service.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\path_alias\AliasManager $path_alias
   *   The alias manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    CurrentPathStack $path_current,
    AccountInterface $current_user,
    LanguageManager $language_manager,
    RequestContext $context,
    AccessManagerInterface $access_manager,
    RequestMatcherInterface $router,
    InboundPathProcessorInterface $path_processor,
    TitleResolverInterface $title_resolver,
    PathMatcherInterface $path_matcher,
    AliasManager $path_alias
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->currentPath = $path_current;
    $this->currentUser = $current_user;
    $this->languageManager = $language_manager;
    $this->context = $context;
    $this->accessManager = $access_manager;
    $this->router = $router;
    $this->pathProcessor = $path_processor;
    $this->titleResolver = $title_resolver;
    $this->pathMatcher = $path_matcher;
    $this->pathAlias = $path_alias;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $attributes): bool {
    $parameters = $attributes->getParameters()->all();

    return !empty($parameters['node']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();

    // Get the node for the current page.
    /** @var \Drupal\node\Entity\Node $node */
    if ($node = $route_match->getParameter('node')) {
      if ($node->bundle() === 'operator') {
        $this->applyBreadcrumbByOperator($breadcrumb, $node);
      }
      elseif ($node->bundle() === 'page') {
        $this->applyBreadcrumbByPath($breadcrumb, $node);
      }
    }
    else {
      $this->applyBreadcrumbByPath($breadcrumb);
    }

    $breadcrumb->addCacheContexts(['route']);

    return $breadcrumb;
  }

  /**
   * Build Breadcrumbs from current path parameters.
   *
   * @param \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb
   *   Breadcrumb instance.
   * @param \Drupal\node\Entity\Node|null $node
   *   Current node instance.
   */
  private function applyBreadcrumbByPath(Breadcrumb &$breadcrumb, Node $node = NULL) {
    $links = [];
    // Add the url.path.parent cache context. This code ignores the last path
    // part so the result only depends on the path parents.
    $breadcrumb->addCacheContexts(['url.path.parent', 'url.path.is_front']);

    // Do not display a breadcrumb on the frontpage.
    if ($this->isFrontPage()) {
      $breadcrumb->setLinks([]);
      return;
    }

    if ($node) {
      $url = Url::fromRoute('entity.node.canonical', [
        'node' => $node->id(),
      ]);

      $links[] = new Link($node->label(), $url);
    }

    // General path-based breadcrumbs. Use the actual request path, prior to
    // resolving path aliases, so the breadcrumb can be defined by simply
    // creating a hierarchy of path aliases.
    $path = trim($this->context->getPathInfo(), '/');
    $path_elements = explode('/', $path);
    $exclude = [];

    // Don't show a link to the front-page path.
    $front = $this->configFactory->get('system.site')->get('page.front');
    $exclude[$front] = TRUE;

    // /user is just a redirect, so skip it.
    $exclude['/user'] = TRUE;
    $exclude['/node'] = TRUE;

    while (count($path_elements) > 1) {
      array_pop($path_elements);

      // Copy the path elements for up-casting.
      $route_request = $this->getRequestForPath(
        '/' . implode('/', $path_elements),
        $exclude
      );

      if ($route_request) {
        $route_match = RouteMatch::createFromRequest($route_request);

        $access = $this->accessManager->check(
          $route_match,
          $this->currentUser,
          NULL,
          TRUE
        );

        // The set of breadcrumb links depends on the access result, so merge
        // the access result's cacheability metadata.
        $breadcrumb = $breadcrumb->addCacheableDependency($access);

        if ($access->isAllowed()) {
          $title = $this->titleResolver->getTitle(
            $route_request,
            $route_match->getRouteObject()
          );

          if (!isset($title)) {
            // Fallback to using the raw path component as the title if the
            // route is missing a _title or _title_callback attribute.
            $title = str_replace(
              ['-', '_'],
              ' ',
              ucfirst(end($path_elements))
            );
          }

          if ($route_match instanceof RouteMatchInterface) {
            $url = Url::fromRouteMatch($route_match);
            $links[] = new Link($title, $url);
          }
        }
      }
    }

    // Add the Home link.
    $links[] = Link::createFromRoute(
      $this->t('Home'),
      '<front>'
    );

    $breadcrumb->setLinks(array_reverse($links));
  }

  /**
   * Matches a path in the router.
   *
   * @param string $path
   *   The request path with a leading slash.
   * @param array $exclude
   *   An array of paths or system paths to skip.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   A populated request object or NULL if the path couldn't be matched.
   */
  protected function getRequestForPath($path, array $exclude) {
    if (!empty($exclude[$path])) {
      return NULL;
    }

    $request = Request::create($path);

    // Performance optimization: set a short accept header to reduce overhead in
    // AcceptHeaderMatcher when matching the request.
    $request->headers->set('Accept', 'text/html');

    // Find the system path by resolving aliases, language prefix, etc.
    $processed = $this->pathProcessor->processInbound($path, $request);

    if (empty($processed) || !empty($exclude[$processed])) {
      // This resolves to the front page, which we already add.
      return NULL;
    }

    $this->currentPath->setPath($processed, $request);

    // Attempt to match this path to provide a fully built request.
    try {
      $request->attributes->add(
        $this->router->matchRequest($request)
      );

      return $request;
    }
    catch (
      ParamNotConvertedException
      | ResourceNotFoundException
      | MethodNotAllowedException
      | AccessDeniedHttpException $e
    ) {
      return NULL;
    }
  }

  /**
   * Check current page is front.
   *
   * @return bool
   *   Result.
   */
  private function isFrontPage(): bool {
    if ($this->pathMatcher->isFrontPage()) {
      return TRUE;
    }

    $alias = $this->pathAlias->getAliasByPath($this->currentPath->getPath());
    $front = $this->configFactory->get('system.site')->get('page.front');

    return $alias === $front;
  }

  /**
   * Build Breadcrumbs for operators.
   *
   * @param \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb
   *   Breadcrumb instance.
   * @param \Drupal\node\Entity\Node|null $node
   *   Current node instance.
   */
  private function applyBreadcrumbByOperator(Breadcrumb $breadcrumb, Node $node) {
    $breadcrumb->addLink(
      Link::createFromRoute(
        $this->t('Home'),
        '<front>'
      )
    );

    $news_page_id = 5;

    $breadcrumb->addLink(
      Link::createFromRoute(
        $this->t('Plan Your Trip'),
        'entity.node.canonical',
        ['node' => $news_page_id]
      )
    );

    $breadcrumb->addLink(
      Link::createFromRoute(
        $node->label(),
        'entity.node.canonical',
        ['node' => $node->id()]
      )
    );
  }

}
