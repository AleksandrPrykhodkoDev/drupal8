<?php

namespace Drupal\nunavut_core;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\token\TokenInterface;
use enshrined\svgSanitize\Sanitizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Class MediaHelper - additional methods to work with media.
 *
 * @package Drupal\nunavut_core
 */
class MediaHelper implements MediaHelperInterface {

  use LoggerChannelTrait;

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
   * Drupal\Core\File\FileSystemInterface definition.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Drupal\token\TokenInterface definition.
   *
   * @var \Drupal\token\TokenInterface
   */
  protected TokenInterface $token;

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
   * The Svg Sanitizer Instance.
   *
   * @var \enshrined\svgSanitize\Sanitizer
   */
  protected Sanitizer $svgSanitizer;

  /**
   * The DOMDocument definition.
   *
   * @var \DOMDocument
   */
  protected \DOMDocument $domDocument;

  /**
   * The MIME type guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected MimeTypeGuesserInterface $mimeTypeGuesser;

  /**
   * Constructs a new MediaHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The ConfigFactory instance.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The FileSystem instance.
   * @param \Drupal\token\TokenInterface $token
   *   The Token instance.
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $mime_type_guesser
   *   The MIME type guesser.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    TokenInterface $token,
    MimeTypeGuesserInterface $mime_type_guesser
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->token = $token;

    $this->fileStorage = $this->entityTypeManager->getStorage('file');
    $this->mediaStorage = $this->entityTypeManager->getStorage('media');

    $this->logger = $this->getLogger('nunavut_core');
    $this->svgSanitizer = new Sanitizer();
    $this->domDocument = new \DOMDocument();
    $this->mimeTypeGuesser = $mime_type_guesser;
  }

  /**
   * Gets the media image url or svg code.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media object.
   *
   * @return array
   *   Media url, svg code.
   */
  public function getImageUrl(Media $media): array {
    $url = '';
    $svg = '';

    if (
      $media->hasField('field_svg_image')
      && !$media->get('field_svg_image')->isEmpty()
    ) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get('field_svg_image')->entity;

      $uri = $file->getFileUri();
      $svg = $this->loadSvgFile($file);

      $url = Url::fromUri(file_create_url($uri))
        ->toString();
    }

    if (
      $media->hasField('field_svg_code')
      && !$media->get('field_svg_code')->isEmpty()
    ) {
      $svg = $this
        ->svgSanitizer
        ->sanitize(
          $media->get('field_svg_code')->getString()
        );
    }

    if ($media->hasField('field_media_image')) {
      $file_id = $media->get('field_media_image')->target_id;
      /** @var \Drupal\file\Entity\File $file */
      if ($file_id && $file = $this->fileStorage->load($file_id)) {
        $url = $file->createFileUrl(FALSE);
      }
    }

    return [
      'url' => $url,
      'svg' => $svg,
    ];
  }

  /**
   * Gets the media image attributes.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media object.
   *
   * @return array
   *   Image attributes.
   */
  public function getImageAttributes(Media $media): array {
    if ($field = $this->getMediaImageSourceFieldName($media)) {
      if ($media->hasField($field) && !$media->get($field)->isEmpty()) {
        try {
          /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
          $item = $media->get($field)->first();

          return $item->getProperties();
        }
        catch (MissingDataException $e) {
          $this->logger->alert($e->getMessage());

          return [];
        }
      }
    }

    return [];
  }

  /**
   * Load media for background.
   *
   * @param \Drupal\media\Entity\Media|object|null $media
   *   Media object.
   *
   * @return array
   *   Background.
   */
  public function loadBackground(Media $media): array {
    if ($media instanceof Media) {
      if (in_array($media->bundle(), ['image', 'card'])) {
        return $this->getStyledImageData($media, ['scale_192', 'towebp']);
      }

      if ($media->bundle() == 'video') {
        // @todo add loading video.
      }

      if ($media->bundle() == 'remote_video') {
        // @todo add loading remote_video.
      }
    }

    return [];
  }

  /**
   * Gets the media image url or svg code.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media object.
   * @param array $image_styles
   *   Image styles name.
   *
   * @return array
   *   Media url, svg code.
   */
  public function getStyledImageData(Media $media, array $image_styles): array {
    $style_urls = [];
    $svg = '';
    $base64 = '';
    $original_url = '';

    if (
      ($imagefield = $this->getMediaImageSourceFieldName($media))
      && $media->hasField($imagefield)
      && !$media->get($imagefield)->isEmpty()
    ) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get($imagefield)->entity;

      $image_uri = $file->getFileUri();

      $original_url = Url::fromUri(file_create_url($image_uri))
        ->toString();

      if (!empty($image_styles) && !$this->isSvgFile($file)) {
        $style_urls = $this->applyImageStyle($image_uri, $image_styles);
      }

      $base64 = $this->imgToBase64($image_uri);

      if ($this->isSvgFile($file)) {
        $svg = $this->loadSvgFile($file);
      }
    }

    if (
      $media->hasField('field_svg_image')
      && !$media->get('field_svg_image')->isEmpty()
    ) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get('field_svg_image')->entity;

      $uri = $file->getFileUri();
      $base64 = $this->imgToBase64($uri);
      $svg = $this->loadSvgFile($file);
      $original_url = Url::fromUri(file_create_url($uri))
        ->toString();
    }

    if (
      $media->hasField('field_svg_code')
      && !$media->get('field_svg_code')->isEmpty()
    ) {
      $svg = $this
        ->svgSanitizer
        ->sanitize(
          $media->get('field_svg_code')->getString()
        );
    }

    return [
      'base64' => $base64,
      'style' => $style_urls,
      'svg' => $svg,
      'url' => $original_url,
    ];
  }

  /**
   * Convert image file to base64.
   *
   * @param string $uri
   *   Uri string.
   *
   * @return string
   *   Base64 string.
   */
  public function imgToBase64(string $uri): string {
    $image_type = $this->mimeTypeGuesser->guess($uri);
    $absolute_path = $this->fileSystem->realpath($uri);
    $image_file = file_get_contents($absolute_path);
    $base_64_image = base64_encode($image_file);

    return sprintf(
      'data:%s;base64,%s',
      $image_type,
      $base_64_image
    );
  }

  /**
   * Applies Image Styles to uri and convert styled files to base64.
   *
   * @param string $image_uri
   *   The original image uri.
   * @param array $styles
   *   The style names.
   *
   * @return array
   *   Array of url and base64 strings, grouped by style name.
   */
  public function applyImageStyle(string $image_uri, array $styles): array {
    $result = [];

    foreach ($styles as $image_style) {
      /** @var \Drupal\image\Entity\ImageStyle $style */
      try {
        $style = $this
          ->entityTypeManager
          ->getStorage('image_style')
          ->load($image_style);

        if ($style) {
          $uri = $style->buildUri($image_uri);

          if (!file_exists($uri)) {
            $style->createDerivative($image_uri, $uri);
          }

          $result[$image_style]['url'] = Url::fromUri(file_create_url($uri))
            ->toString();

          $result[$image_style]['base64'] = $this->imgToBase64($uri);
        }
      }
      catch (
        InvalidPluginDefinitionException
        | PluginNotFoundException $e
      ) {
        $this->logger->error('Error with loading image style');
        $result[$image_style]['url'] = '';
        $result[$image_style]['base64'] = '';
      }
    }

    return $result;
  }

  /**
   * Checks if current file is SVG image.
   *
   * @param \Drupal\file\Entity\File $file
   *   File to check.
   *
   * @return bool
   *   TRUE if is SVG, FALSE otherwise.
   */
  public function isSvgFile(File $file): bool {
    return $file->getMimeType() === 'image/svg+xml';
  }

  /**
   * Loads and sanitize svg code.
   *
   * @param \Drupal\file\Entity\File $file
   *   Svg file.
   *
   * @return false|string
   *   Svg markup.
   */
  public function loadSvgFile(File $file) {
    $data = file_get_contents($file->getFileUri());

    return $this->sanitizeSvgStyle(
      $this->svgSanitizer->sanitize($data),
      $file->id()
    );
  }

  /**
   * Add  attributes to svg tag.
   *
   * @param string $svg
   *   The svg string.
   * @param string $id_suffix
   *   Filename.
   *
   * @return false|string
   *   Svg markup.
   */
  public function sanitizeSvgStyle(string $svg, string $id_suffix) {
    $this
      ->domDocument
      ->loadXML($svg);

    $svg_item = $this
      ->domDocument
      ->getElementsByTagName('svg')
      ->item(0);

    $new_id = 'Svg_';

    if (empty($svg_item)) {
      return $this
        ->domDocument
        ->saveXML();
    }

    if ($svg_item->hasAttribute('id')) {
      $id = $svg_item->getAttribute('id');
      $new_id = $new_id . $svg_item->getAttribute('id');
    }

    $new_id .= '_' . $id_suffix;
    $svg_item->setAttribute('id', $new_id);

    $svg = $this
      ->domDocument
      ->saveXML();

    // $svg = str_replace('#' . $id, '#' . $new_id, $svg);
    $this
      ->domDocument
      ->loadXML($svg);

    $style = $this
      ->domDocument
      ->getElementsByTagName('style');

    for ($i = 0; $i < $style->count(); $i++) {
      $style_value = $style
        ->item($i)
        ->nodeValue;

      if (isset($id)) {
        $style_value = str_replace(
          "#{$id} ",
          "#{$new_id} ",
          $style_value
        );
      }

      $styles = explode('}', $style_value);

      foreach ($styles as $key => $style_item) {
        $style_item = trim($style_item);

        if (substr($style_item, 0, 1) == '.') {
          $styles[$key] = "#{$new_id} {$style_item}";
        }
      }

      $style->item($i)->nodeValue = implode('}', $styles);
    }

    return $this
      ->domDocument
      ->saveXML();
  }

  /**
   * Gets Source Field Name from image source.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media object.
   *
   * @return mixed
   *   Field name if source is image or false.
   */
  public function getMediaImageSourceFieldName(Media $media) {
    if ($media->getSource()->getPluginId() == 'image') {
      return $media->getSource()->getConfiguration()['source_field'];
    }

    return NULL;
  }

}
