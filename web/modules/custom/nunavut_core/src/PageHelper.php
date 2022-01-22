<?php

namespace Drupal\nunavut_core;

use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class PageHelper.
 *
 * Provides service for operating with pages.
 *
 * @package Drupal\nunavut_core
 */
class PageHelper {

  use LoggerChannelTrait;
  use StringTranslationTrait;
  use MessengerTrait;

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
   * The media storage.
   *
   * @var \Drupal\media\Entity\Media
   */
  protected $mediaStorage;

  /**
   * The File storage.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $fileStorage;

  /**
   * The Logger Interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The MediaHelper.
   *
   * @var \Drupal\nunavut_core\MediaHelper
   */
  protected MediaHelper $mediaHelper;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected TitleResolverInterface $titleResolver;

  /**
   * The Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $renderer;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The alias manager object.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected CountryRepositoryInterface $countryRepository;

  /**
   * The subdivision repository.
   *
   * @var \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface
   */
  protected SubdivisionRepositoryInterface $subdivisionRepository;

  /**
   * Constructs a new MediaHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The ConfigFactory instance.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Retrieves the currently active route match object.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Retrieves the request stack.
   * @param \Drupal\nunavut_core\MediaHelper $media_helper
   *   The MediaHelper instance.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The Renderer.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   * @param \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface $subdivision_repository
   *   The subdivision repository.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    RouteMatchInterface $route_match,
    RequestStack $request_stack,
    MediaHelper $media_helper,
    TitleResolverInterface $title_resolver,
    Renderer $renderer,
    AliasManagerInterface $alias_manager,
    CountryRepositoryInterface $country_repository,
    SubdivisionRepositoryInterface $subdivision_repository
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
    $this->mediaHelper = $media_helper;
    $this->titleResolver = $title_resolver;
    $this->renderer = $renderer;
    $this->aliasManager = $alias_manager;
    $this->countryRepository = $country_repository;
    $this->subdivisionRepository = $subdivision_repository;

    $this->fileStorage = $this->entityTypeManager->getStorage('file');
    $this->mediaStorage = $this->entityTypeManager->getStorage('media');

    $this->logger = $this->getLogger('nunavut_core');

    $this->config = $this->configFactory->get('nunavut_core.settings');
  }

  /**
   * Extends page variables.
   *
   * @param array $variables
   *   Pointer to $variables or other array for store results.
   * @param \Drupal\node\Entity\Node|null $node
   *   Node object.
   */
  public function defaultVariables(array &$variables, $node = NULL): void {
    if (!$node) {
      /** @var \Drupal\node\Entity\Node $node */
      $node = $this->routeMatch->getParameter('node');
    }

    $variables['page_background_image'] = $this->getBackground($node, 'field_background_image');
    $variables['page_header_border'] = $this->getBorderColor($node);
    $variables['header_titles'] = $this->getHeroText($node);
    $variables['covid_url'] = $this->config->get('covid_page')['url'] ?? NULL;

    if ($node && $node->hasField('field_content_background')
      && !$node->get('field_content_background')->isEmpty()
    ) {
      $variables['page_content_background'] = $this->getBackground($node, 'field_content_background');
    }

    if ($node && $node->getType() == 'package') {
      if ($node->hasField('field_background_image')
        && !$node->get('field_background_image')->isEmpty()
      ) {
        $variables['page_background_image'] = $this->getBackground(
          $node,
          'field_background_image'
        );
      }
      elseif ($node->hasField('field_package_media')
        && !$node->get('field_package_media')->isEmpty()
      ) {
        $variables['page_background_image'] = $this->getBackground(
          $node,
          'field_package_media'
        );
      }
      else {
        $variables['page_background_image'] = $this->getBackground(
          $node,
          'field_package_carousel_images'
        );
      }
    }
  }

  /**
   * Load page background from Node instance or default configuration.
   *
   * @param \Drupal\node\Entity\Node|null $node
   *   Node instance.
   * @param string $field_name
   *   Field name for background.
   *
   * @return array
   *   Background array.
   *
   * @see \Drupal\nunavut_core\MediaHelper::loadBackground()
   */
  protected function getBackground(?Node $node, string $field_name): array {
    if ($node && $node->hasField($field_name)) {
      /** @var \Drupal\media\Entity\Media $media */
      if ($media = $node->get($field_name)->entity) {
        return $this->mediaHelper->loadBackground($media);
      }
    }
    // Loading default background color from configuration.
    $settings = $this->config->get('page_settings');
    $backgrounds = explode(',', $settings['background_image']);
    $index = array_keys($backgrounds)[rand(0, count($backgrounds) - 1)];
    try {
      /** @var \Drupal\media\Entity\Media $media */
      $media = $this->mediaStorage
        ->load($backgrounds[$index]);
      return $this->mediaHelper->loadBackground($media);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->alert($e->getMessage());
      return [];
    }
  }

  /**
   * Loads border color from node or config.
   *
   * @param \Drupal\node\Entity\Node|null $node
   *   Node instance.
   *
   * @return string
   *   Border-color class name.
   */
  protected function getBorderColor(?Node $node): string {
    if ($node && $node->hasField('field_background_color')) {
      return preg_replace(
        '/bg-/',
        'border-',
        $node->get('field_background_color')->getString()
      );
    }

    // Loading default background color from configuration.
    $settings = $this->config->get('page_settings');
    return $settings['page_border'];
  }

  /**
   * Loads Hero text from node or config.
   *
   * @param \Drupal\node\Entity\Node|null $node
   *   Node instance.
   *
   * @return array
   *   Hero text strings.
   */
  private function getHeroText(?Node $node): array {
    if ($node && $node->hasField('field_hero_text')
      && !$node->get('field_hero_text')->isEmpty()
    ) {
      $result = $node->get('field_hero_text')->getValue();
    }
    elseif ($node) {
      $result[] = ['value' => $node->label()];
    }
    else {
      $title = $this->titleResolver
        ->getTitle(
          $this->requestStack->getCurrentRequest(),
          $this->routeMatch->getRouteObject()
        );
      $result[] = ['value' => $title];
    }

    return $result;
  }

  /**
   * Loads Hero text from node or config.
   *
   * @param \Drupal\node\Entity\Node|null $node
   *   Node instance.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function prepareRenderRoundedPageCard(?Node $node): array {
    if ($node->getType() === 'page') {
      $vars = [];
      $result['#theme'] = 'nunavut_page_rounded_card';
      $this->defaultVariables($vars, $node);

      $result['#card_border'] = $vars['page_header_border'];
      $result['#card_bg_url'] = $vars['page_background_image'];

      $result['#title'] = $node->label();

      $result['#card_content'] = $node->hasField('body')
      && !$node->get('body')->isEmpty()
        ? $node->get('body')->first()->getValue()['value']
        : '';

      $result['#card_button'] = [
        'url' => $this->aliasManager->getAliasByPath('/node/' . $node->id()),
        'text' => $this->config->get('cards_settings')['page_more_label'],
      ];

      return $result;
    }

    return [];
  }

  /**
   * Loads Hero text from node or config.
   *
   * @param \Drupal\node\Entity\Node|null $node
   *   Node instance.
   *
   * @return array
   *   Renderable array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function prepareRenderPhotoPage(?Node $node): array {
    $result = [];

    if ($node->getType() === 'page') {
      $result['#theme'] = 'nunavut_page_photo_card';

      if ($node->hasField('field_background_image')) {
        /** @var \Drupal\media\Entity\Media $media */
        if ($media = $node->get('field_background_image')->entity) {
          $result['#card']['image_properties'] = $this
            ->mediaHelper
            ->getImageAttributes($media);

          $result['#card']['image_properties'] += $this
            ->mediaHelper
            ->loadBackground($media);
        }
      }

      $result['#title'] = $node->label();
      $result['#url'] = $this->aliasManager->getAliasByPath('/node/' . $node->id());
    }

    return $result;
  }

  /**
   * Extends page card variables.
   *
   * @param array $variables
   *   Pointer to $variables or other array for store results.
   * @param \Drupal\node\Entity\Node|null $node
   *   Node object.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function cardVariables(array &$variables, $node = NULL): void {
    $variables['card_bg_url'] = $variables['page_background_image'];

    $variables['title'] = $node ? $node->label() : '';

    $variables['card_content'] = $node
    && $node->hasField('body')
    && !$node->get('body')->isEmpty()
      ? $node->get('body')->first()->getValue()['value']
      : '';

    $read_more = $this->config->get('cards_settings')[$node->getType() . '_more_label'] ?? $this->t('Learn More');

    $variables['card_button'] = [
      'url' => $variables['url'],
      'text' => $read_more,
    ];
  }

  /**
   * Operator contact variables.
   *
   * @param \Drupal\node\Entity\Node|null $node
   *   Node object.
   *
   * @return array
   *   Operator contacts.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function operatorContacts($node = NULL): array {
    $result = [];

    if (!$node || $node->getType() != 'operator') {
      return $result;
    }

    $title = '';
    $first_name = '';

    if ($node->hasField('field_operator_contact_fn')) {
      $title = $node
        ->get('field_operator_contact_title')
        ->getString();
    }

    if ($node->hasField('field_operator_contact_fn')) {
      $first_name = $node
        ->get('field_operator_contact_fn')
        ->getString();
    }

    $result['title'] = [
      '#type' => 'inline_template',
      '#template' => '<span>{{ name }} {{ surname }}</span>',
      '#context' => [
        'name' => $first_name,
        'surname' => $title,
      ],
    ];

    $address = $node->hasField('field_operator_address')
    && !$node->get('field_operator_address')->isEmpty()
      ? $node->get('field_operator_address')->first()->getValue()
      : NULL;

    if ($address) {
      $countries = $this->countryRepository->getList();
      $subdivision = $this->subdivisionRepository->getList([$address['country_code']]);
      $result['address'] = [
        'address_line1' => $address['address_line1'],
        'address_line2' => $address['address_line2'],
        'postal_code' => $address['postal_code'],
        'locality' => $address['locality'],
        'administrative_area' => $subdivision[$address['administrative_area']],
        'country' => $countries[$address['country_code']],
      ];
    }

    $result['phone'] = $node->hasField('field_operator_phone')
    && !$node->get('field_operator_phone')->isEmpty()
      ? $node->get('field_operator_phone')->getString()
      : NULL;

    $result['alt_phone'] = $node->hasField('field_operator_alt_phone')
    && !$node->get('field_operator_alt_phone')->isEmpty()
      ? $node->get('field_operator_alt_phone')->getString()
      : NULL;

    $result['email'] = $node->hasField('field_operator_email')
    && !$node->get('field_operator_email')->isEmpty()
      ? $node->get('field_operator_email')->getString()
      : NULL;

    $result['web'] = $node->hasField('field_operator_web_url')
    && !$node->get('field_operator_web_url')->isEmpty()
      ? $node->get('field_operator_web_url')->getString()
      : NULL;

    return $result;
  }

  /**
   * Operator contact variables.
   *
   * @param \Drupal\node\Entity\Node|null $node
   *   Node object.
   *
   * @return array
   *   Operator contacts.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function operatorContactsRendered($node = NULL): array {
    $contacts = $this->operatorContacts($node);

    // Parse url of website for short title display.
    if (!empty($contacts['web'])) {
      $host = parse_url(
        $contacts['web'],
        PHP_URL_HOST
      );

      $contacts['web'] = [
        'url' => $contacts['web'],
        'title' => $host,
      ];
    }

    return [
      '#theme' => 'nunavut_contacts',
      '#operator' => [
        'contact' => $contacts,
      ],
    ];
  }

  /**
   * Check that term has child with some name.
   *
   * @param \Drupal\taxonomy\Entity\Term|null $term
   *   Term for check.
   *
   * @return bool
   *   Returns TRUE if term has child with some name.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function isTermParentRepeat(Term $term = NULL): bool {
    if ($term) {
      $children = $this
        ->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadChildren($term->id());

      foreach ($children as $child) {
        if ($child->label() == $term->label()) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
