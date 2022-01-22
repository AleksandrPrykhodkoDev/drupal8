<?php

namespace Drupal\nunavut_core;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MediaOperations class to handle Media functionality.
 */
class MediaOperations implements ContainerInjectionInterface {

  use StringInflectorTrait;
  use LoggerChannelTrait;
  use MessengerTrait;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A proxied implementation of AccountInterface.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destination;

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Logger Interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The MediaHelper service.
   *
   * @var \Drupal\nunavut_core\MediaHelper|object|null
   */
  protected $mediaHelper;

  /**
   * The PageHelper service.
   *
   * @var \Drupal\nunavut_core\PageHelper|object|null
   */
  protected $pageHelper;

  /**
   * MediaOperations constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The global Drupal container.
   */
  public function __construct(ContainerInterface $container) {
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->currentUser = $container->get('current_user');
    $this->destination = $container->get('redirect.destination');
    $this->logger = $this->getLogger('nunavut_core');
    $this->mediaHelper = $container->get('nunavut_core.media_helper');
    $this->pageHelper = $container->get('nunavut_core.page_helper');

    $this->config = $container->get('config.factory')
      ->get('nunavut_core.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container);
  }

  /**
   * Media preprocess.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_media()
   */
  public function preprocess(array &$variables): void {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $variables['media'];

    $this->invokeInflectorMethods($media, __FUNCTION__, [&$variables]);
  }

  /**
   * Media preprocess.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_media()
   */
  protected function preprocessMediaCard(array &$variables): void {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $variables['media'];

    $variables['card_bg_url'] = $this->mediaHelper->loadBackground($media);
    $variables['title'] = $media->label();
    $variables['card_content'] = $media->hasField('field_teaser')
      ? $media->get('field_teaser')->getString()
      : '';

    $link_url = '';
    $title = '';

    if ($media->hasField('field_link')) {
      try {
        /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link */
        if ($link = $media->get('field_link')->first()) {
          $link_url = $link->getUrl()->toString();
          $title = $link->get('title')->getString();
        }
      }
      catch (MissingDataException $e) {
        $this->logger->alert(
          'Paragraph @id has missing url or title',
          ['@id' => $media->id()]
        );
      }
    }

    $variables['card_button'] = [
      'url' => $link_url,
      'text' => $title,
    ];
  }

  /**
   * Media preprocess.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_media()
   */
  protected function preprocessMediaDiscoveryNunavut(array &$variables): void {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $variables['media'];

    $page_variables = [];
    $this->pageHelper->defaultVariables($page_variables);
    $variables['border_color'] = $page_variables['page_header_border'];

    $field_name = $media->getSource()->getConfiguration()['source_field'];

    /** @var \Drupal\media\Entity\Media $image */
    $image = $media->get($field_name)->entity;

    $variables['image'] = $this
      ->mediaHelper
      ->getStyledImageData(
        $image,
        ['scale_192', 'discovery_slider', 'discovery_slider_webp']
      );

    $variables['image'] += $this
      ->mediaHelper
      ->getImageAttributes($image);

    $variables['tooltip'] = $media->hasField('field_tooltip')
      ? $media->get('field_tooltip')->getString()
      : '';

    $url = '';
    $title = '';

    if (
      $media->hasField('field_link')
      && !$media->get('field_link')->isEmpty()
    ) {
      try {
        /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $link */
        if ($link = $media->get('field_link')->first()) {
          $url = $link->getUrl()->toString();
          $title = $link->get('title')->getString();
        }
      }
      catch (MissingDataException $e) {
        $url = NULL;
        $this->logger->alert(
          'Paragraph @id has missing url or title',
          ['@id' => $media->id()]
        );
      }
    }

    $variables['link'] = [
      'url' => $url,
      'title' => $title,
    ];
  }

}
