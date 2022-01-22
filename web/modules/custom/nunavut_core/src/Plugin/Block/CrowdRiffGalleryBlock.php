<?php

namespace Drupal\nunavut_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a crowdriff gallery block.
 *
 * @Block(
 *   id = "nunavut_core_crowdriff_gallery",
 *   admin_label = @Translation("CrowdRiff Gallery"),
 *   category = @Translation("Nunavut")
 * )
 */
class CrowdRiffGalleryBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new CrowdRiffGalleryBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configFactory = $config_factory;
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
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    $crowdriff_settings = $this
      ->configFactory
      ->get('nunavut_core.settings')
      ->get('crowdriff_settings');

    if ($gallery_code = $crowdriff_settings['gallery_code'] ?? NULL) {
      $build['content'] = [
        '#type' => 'inline_template',
        '#template' => '<script id="cr-init__{{gallery_code}}" src="https://starling.crowdriff.com/js/crowdriff.js" async></script>',
        '#context' => [
          'gallery_code' => $gallery_code,
        ],
      ];
    }

    return $build;
  }

}
