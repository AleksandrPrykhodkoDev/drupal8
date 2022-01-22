<?php

namespace Drupal\nunavut_core;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * NodeOperations class to handle Node functionality.
 */
class NodeOperations implements ContainerInjectionInterface {

  use StringInflectorTrait;
  use LoggerChannelTrait;
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A proxy implementation of AccountInterface.
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
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected $countryRepository;

  /**
   * The subdivision repository.
   *
   * @var \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface
   */
  protected $subdivisionRepository;

  /**
   * NodeOperations constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The global Drupal container.
   */
  public function __construct(ContainerInterface $container) {
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->currentUser = $container->get('current_user');
    $this->destination = $container->get('redirect.destination');
    $this->mediaHelper = $container->get('nunavut_core.media_helper');
    $this->logger = $this->getLogger('nunavut_core');
    $this->pageHelper = $container->get('nunavut_core.page_helper');
    $this->countryRepository = $container->get('address.country_repository');
    $this->subdivisionRepository = $container->get('address.subdivision_repository');

    $this->config = $container
      ->get('config.factory')
      ->get('nunavut_core.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container);
  }

  /**
   * Node preprocess.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_node()
   */
  public function preprocess(array &$variables): void {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $variables['node'];

    $this->invokeInflectorMethods($node, __FUNCTION__, [&$variables]);
  }

  /**
   * Preprocess node page.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @see hook_preprocess_node()
   */
  protected function preprocessNodePage(array &$variables): void {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $variables['node'];

    $this->pageHelper->defaultVariables($variables, $node);
    $this->pageHelper->cardVariables($variables, $node);

    if ($node->hasField('field_background_image')) {
      /** @var \Drupal\media\Entity\Media $media */
      if ($media = $node->get('field_background_image')->entity) {
        $variables['card']['image_properties'] = $this
          ->mediaHelper
          ->getImageAttributes($media);

        $variables['card']['image_properties'] += $this
          ->mediaHelper
          ->loadBackground($media);

        $variables['teaser_photo']['image_properties'] = $this
          ->mediaHelper
          ->getImageAttributes($media);

        $variables['teaser_photo']['image_properties'] += $this
          ->mediaHelper
          ->getStyledImageData($media, ['medium', 'mediumwebp']);
      }
    }
  }

  /**
   * Preprocess node story.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @see hook_preprocess_node()
   */
  protected function preprocessNodeStory(array &$variables): void {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $variables['node'];

    $this->pageHelper->defaultVariables($variables, $node);
    $this->pageHelper->cardVariables($variables, $node);

    if ($node->hasField('field_background_image')) {
      /** @var \Drupal\media\Entity\Media $media */
      if ($media = $node->get('field_background_image')->entity) {
        $variables['teaser_photo']['image_properties'] = $this
          ->mediaHelper
          ->getImageAttributes($media);

        $variables['teaser_photo']['image_properties'] += $this
          ->mediaHelper
          ->getStyledImageData($media, ['medium', 'mediumwebp']);
      }
    }
  }

  /**
   * Preprocess node package.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @see hook_preprocess_node()
   */
  protected function preprocessNodePackage(array &$variables): void {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $variables['node'];

    $this->pageHelper->defaultVariables($variables, $node);
    $this->pageHelper->cardVariables($variables, $node);

    if ($node->hasField('field_package_media')) {
      /** @var \Drupal\media\Entity\Media $media */
      if ($media = $node->get('field_package_media')->entity) {
        $variables['teaser_photo']['image_properties'] = $this
          ->mediaHelper
          ->getImageAttributes($media);

        $variables['teaser_photo']['image_properties'] += $this
          ->mediaHelper
          ->getStyledImageData($media, ['medium', 'mediumwebp']);
      }
    }

    $variables['card_price'] = $node->hasField('field_package_price')
    && !$node->get('field_package_price')->isEmpty()
      ? $node->get('field_package_price')->getString()
      : '';

    $variables['card_button']['url'] = $variables['url'];

    if ($variables['view_mode'] == 'full') {
      $package['info'] = $node->hasField('body')
      && !$node->get('body')->isEmpty()
        ? $node->get('body')->first()->getValue()['value']
        : '';

      /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $photos */
      $photos = $node->get('field_package_media');

      /** @var \Drupal\media\Entity\Media[] $medias */
      $medias = $photos->referencedEntities();
      foreach ($medias as $media) {
        $attributes = $this
          ->mediaHelper
          ->getImageAttributes($media);

        $attributes += $this
          ->mediaHelper
          ->getStyledImageData($media, [
            'scale_192',
            'package_full',
            'package_full_webp',
          ]);

        $attribute = new Attribute([
          'alt' => $attributes['alt']->getString(),
          'title' => $attributes['title']->getString(),
          'width' => '1153',
          'height' => '790',
          'src' => $attributes['style']['scale_192']['base64'],
          'data-src' => $attributes['style']['package_full']['url'],
          'data-webp' => $attributes['style']['package_full_webp']['url'],
          'class' => [
            'lazy-img',
            'img-fluid',
            'nunavut-border-left',
            'border-width-default',
            $variables['page_header_border'],
          ],
        ]);

        $package['photo'][]['attributes'] = $attribute;
      }

      $package['price'] = $node->hasField('field_package_price')
      && !$node->get('field_package_price')->isEmpty()
        ? $node->get('field_package_price')->getString()
        : '';

      $package['after_price'] = $node->hasField('field_post_price_label')
      && !$node->get('field_post_price_label')->isEmpty()
        ? $node->get('field_post_price_label')->getString()
        : '';

      /** @var \Drupal\node\Entity\Node|null $operator */
      $operator = $node->hasField('field_package_parent_operator')
      && !$node->get('field_package_parent_operator')->isEmpty()
        ? $node->get('field_package_parent_operator')->entity
        : NULL;

      if ($operator) {
        $package['social'] = $operator->hasField('field_operator_social_media')
        && !$operator->get('field_operator_social_media')->isEmpty()
          ? $operator->get('field_operator_social_media')->view([])
          : NULL;

        $package['contact'] = $this->pageHelper->operatorContactsRendered($operator);
      }

      $variables['package'] = $package;
    }
  }

  /**
   * Preprocess node Operator.
   *
   * @param array &$variables
   *   A pre render structure array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @see hook_preprocess_node()
   */
  protected function preprocessNodeOperator(array &$variables): void {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $variables['node'];

    $this->pageHelper->defaultVariables($variables, $node);
    $this->pageHelper->cardVariables($variables, $node);

    if ($node->hasField('field_operator_photo_file')) {
      /** @var \Drupal\media\Entity\Media $media */
      if ($media = $node->get('field_operator_photo_file')->entity) {
        $variables['teaser_photo']['image_properties'] = $this
          ->mediaHelper
          ->getImageAttributes($media);

        $variables['teaser_photo']['image_properties'] += $this
          ->mediaHelper
          ->getStyledImageData($media, ['medium', 'mediumwebp']);
      }
    }

    if ($variables['view_mode'] == 'full') {
      $operator['summary'] = $node->hasField('body')
      && !$node->get('body')->isEmpty()
        ? $node->get('body')->first()->getValue()['summary']
        : '';

      $operator['description'] = $node->hasField('body')
      && !$node->get('body')->isEmpty()
        ? $node->get('body')->first()->getValue()['value']
        : '';

      /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $photos */
      $photos = $node->get('field_operator_photo_file');
      $medias = $photos->referencedEntities();
      /** @var \Drupal\media\Entity\Media $media */
      foreach ($medias as $media) {
        $attributes = $this
          ->mediaHelper
          ->getImageAttributes($media);

        $attributes += $this
          ->mediaHelper
          ->getStyledImageData($media, [
            'scale_192',
            'operator_photo',
            'operator_photo_webp',
          ]);

        $attribute = new Attribute([
          'alt' => $attributes['alt']->getString(),
          'title' => $attributes['title']->getString(),
          'width' => '766',
          'height' => '767',
          'src' => $attributes['style']['scale_192']['base64'],
          'data-src' => $attributes['style']['operator_photo']['url'],
          'data-webp' => $attributes['style']['operator_photo']['url'],
          'class' => [
            'lazy-img',
            'img-fluid',
          ],
        ]);

        $operator['photos'][]['attributes'] = $attribute;
      }

      $operator['contacts'] = $this->pageHelper->operatorContactsRendered($node);

      $operator['packages'] = views_embed_view('packages', 'operator_packages', $node->id());
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery('AND');

      $operator['packages_count'] = $query
        ->condition('status', 1)
        ->condition('type', 'package')
        ->condition('field_package_parent_operator', $node->id())
        ->count()
        ->execute();

      $variables['operator'] = $operator;
    }
  }

}
