<?php

namespace Drupal\lilote_membership\Controller;

use Drupal\commerce_price\Price;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a modal to quit a quiz.
 */
class MembershipSubscriptionController extends ControllerBase {

  /**
   * Subscription order type.
   */
  const ORDER_TYPE = 'default';

  /**
   * Subscription product type.
   */
  const PRODUCT_TYPE = 'subscription';

  /**
   * Product variation type.
   */
  const VARIATION_TYPE = 'year_subscription';

  /**
   * Product variation's SKU.
   */
  const VARIATION_SKU = '12M_LIL_SUB';

  /**
   * Commerce cart provider.
   *
   * @var \Drupal\commerce_cart\CartProvider
   */
  protected $cartProvider;

  /**
   * Commerce cart manager.
   *
   * @var \Drupal\commerce_cart\CartManager
   */
  protected $cartManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->cartManager = $container->get('commerce_cart.cart_manager');
    $instance->cartProvider = $container->get('commerce_cart.cart_provider');
    return $instance;
  }

  /**
   * Add subscription product to cart and redirect to checkout.
   */
  public function addToCart() {
    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $this->entityTypeManager()->getStorage('commerce_store');
    $store = $store_storage->loadDefault();

    $variations_storage = $this->entityTypeManager()
      ->getStorage('commerce_product_variation');
    $variations = $variations_storage->loadByProperties([
      'type' => self::VARIATION_TYPE,
      'sku' => self::VARIATION_SKU,
    ]);

    if (empty($variations)) {
      $price = new Price(
        '12.00',
        $store->getDefaultCurrency()->getCurrencyCode()
      );
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = $this->entityTypeManager()
        ->getStorage('commerce_product_variation')
        ->create([
          'type' => self::VARIATION_TYPE,
          'title' => '1 year subscription',
          'sku' => self::VARIATION_SKU,
          'status' => TRUE,
          'price' => $price,
        ]);
      $variation->addTranslation('fr', ['title' => "1 an d'abonnement"]);
      $variation->save();

      $product_storage = $this->entityTypeManager()
        ->getStorage('commerce_product');
      $products = $product_storage->loadByProperties([
        'type' => self::PRODUCT_TYPE,
      ]);
      if (empty($products)) {
        /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
        $product = $product_storage->create([
          'type' => self::PRODUCT_TYPE,
          'title' => 'Standard subscription',
          'stores' => [$store],
          'variations' => [$variation],
          'uid' => 2,
          'status' => TRUE,
        ]);
        $product->addTranslation('fr', ['title' => 'Abonnement standard']);
        $product->save();
      }
      else {
        /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
        $product = reset($products);
        $product->addVariation($variation);
        if (!$product->isPublished()) {
          $product->setPublished();
        }
        $product->save();
      }
    }
    else {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = reset($variations);
      if (!$variation->isPublished()) {
        $variation->setPublished();
        $variation->save();
      }
    }

    // Delete existing cart.
    if ($cart = $this->cartProvider->getCart(self::ORDER_TYPE, $store)) {
      $cart->delete();
      $this->cartProvider->clearCaches();
    }
    $cart = $this->cartProvider->createCart(self::ORDER_TYPE, $store);

    // Add new subscription product to cart.
    $this->cartManager->addEntity($cart, $variation);

    // Redirect user to the first step of checkout.
    return $this->redirect('commerce_cart.page');
  }

}
