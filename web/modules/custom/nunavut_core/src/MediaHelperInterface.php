<?php

namespace Drupal\nunavut_core;

use Drupal\media\Entity\Media;

/**
 * Interface MediaHelperInterface - additional functions to work with media.
 */
interface MediaHelperInterface {

  /**
   * Gets the media image url or svg code.
   *
   * @param \Drupal\media\Entity\Media $media
   *   Media object.
   *
   * @return array
   *   Media type, url, svg code.
   */
  public function getImageUrl(Media $media): array;

}
