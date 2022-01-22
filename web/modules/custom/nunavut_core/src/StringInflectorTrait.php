<?php

namespace Drupal\nunavut_core;

use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;

/**
 * Camelizes a word.
 *
 * @see container
 * @ingroup helper
 */
trait StringInflectorTrait {

  /**
   * Camelizes a word.
   *
   * @param string $word
   *   The word to camelize.
   *
   * @return string
   *   The camelized word.
   */
  protected function camelize(string $word): string {
    $inflector = InflectorFactory::createForLanguage(Language::ENGLISH)->build();
    return ucfirst($inflector->camelize($word));
  }

  /**
   * Prepares a inflector sub-method list regarding to the source.
   *
   * @param mixed $source
   *   A view source object.
   * @param string $method
   *   A method name.
   *
   * @return string[]
   *   A inflector method list.
   */
  protected function getInflectorMethods($source, string $method): array {
    $methods = [];

    if (is_string($source)) {
      $methods[] = $method . $this->camelize($source);
    }
    elseif ($source instanceof ViewExecutable) {
      $id = $this->camelize($source->id());
      $display_id = $this->camelize($source->getDisplay()->display['id']);

      $methods[] = $method . 'For' . $id;
      $methods[] = $method . 'For' . $id . 'For' . $display_id;
    }
    elseif ($source instanceof QueryPluginBase) {
      if (!empty($source->tags)) {
        foreach ($source->tags as $tag) {
          $methods[] = $method . 'ByTag' . $this->camelize($tag);
        }
      }
    }
    elseif ($source instanceof EntityInterface) {
      $type = $source->getEntityTypeId();
      $bundle = $source->bundle();

      if ($type) {
        $methods[] = $method . $this->camelize($type);
        if ($bundle) {
          $methods[] = $method . $this->camelize($type . '_' . $bundle);
        }
      }
    }
    elseif (isset($source['#paragraph_type'])) {
      $type = 'paragraph';
      $bundle = $source['#paragraph_type'] ?? NULL;

      $methods[] = $method . $this->camelize($type);
      if ($bundle) {
        $methods[] = $method . $this->camelize($type . '_' . $bundle);
      }
    }
    elseif (isset($source['#entity'])
      && $source['#entity'] instanceof ParagraphInterface) {
      $type = 'paragraph';
      $bundle = $source['#entity']->bundle() ?? NULL;

      $methods[] = $method . $this->camelize($type);
      if ($bundle) {
        $methods[] = $method . $this->camelize($type . '_' . $bundle);
      }
    }

    return $methods;
  }

  /**
   * Invokes a inflector sub-methods regarding to the source.
   *
   * @param mixed $source
   *   A view source object.
   * @param string $method
   *   A method name.
   * @param array $params
   *   An arguments array.
   */
  protected function invokeInflectorMethods($source, string $method, array $params = NULL) {
    foreach ($this->getInflectorMethods($source, $method) as $inflector_method) {
      if (method_exists($this, $inflector_method)) {
        call_user_func_array([$this, $inflector_method], $params);
      }
    }
  }

}
