<?php

namespace Drupal\nunavut_core\Plugin\Block;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\nunavut_core\MediaHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a discovery nunavut block.
 *
 * @Block(
 *   id = "nunavut_core_discovery_nunavut",
 *   admin_label = @Translation("Discovery Nunavut"),
 *   category = @Translation("Nunavut")
 * )
 */
class DiscoveryNunavutBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The nunavut_core.media_helper service.
   *
   * @var \Drupal\nunavut_core\MediaHelper|object|null
   */
  protected $mediaHelper;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a new DiscoveryNunavutBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\nunavut_core\MediaHelper $media_helper
   *   The nunavut_core.media_helper service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Retrieves the currently active route match object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    MediaHelper $media_helper,
    RouteMatchInterface $route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->mediaHelper = $media_helper;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('nunavut_core.media_helper'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    $build = [];
    $slider = [];
    $selected = [];

    try {
      /** @var \Drupal\media\MediaStorage $media_storage */
      $media_storage = $this
        ->entityTypeManager
        ->getStorage('media');

      $ids = $media_storage
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', 'discovery_nunavut')
        ->execute();

      for ($i = 0; $i < 3; $i++) {
        $index = array_keys($ids)[rand(0, count($ids) - 1)];
        $selected[$index] = $ids[$index];
        unset($ids[$index]);
      }

      $medias = $media_storage
        ->loadMultiple($selected);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this
        ->getLogger('nunavut_core')
        ->error($e->getMessage());

      return $build;
    }

    $view_builder = $this
      ->entityTypeManager
      ->getViewBuilder('media');

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($medias as $media) {
      $slider[] = [
        'media' => $view_builder->view($media, 'default'),
      ];
    }

    $build['slider'] = $slider;

    if ($node) {
      $build['node_id'] = $node->id();
    }

    return $build;
  }

}
