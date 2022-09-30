<?php

namespace Drupal\crm_department_balance\Plugin\views\field;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a custom field for Department Balance.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("department_balance_amount")
 */
class DepartmentBalanceAmount extends FieldPluginBase {

  /**
   * The entity type manager service instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $account_proxy) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $account_proxy;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We don't need to modify query for this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $result = [];
    $nid = $values->nid;

    if (!empty($nid)) {
      $amount_balance = 0;
      if ($values->_entity->get('db_amount')->value) {
        $amount_balance = $values->_entity->get('db_amount')->value;
      }
      $currency_balance_value = '';
      if ($values->_entity->get('db_currency')->target_id) {
        $currency_balance_id = $values->_entity->get('db_currency')->target_id;
        $currency_balance = $this->entityTypeManager->getStorage('taxonomy_term')
          ->load($currency_balance_id);
        $currency_balance_value = $currency_balance->getName();
      }

      $result['amount'] = [
        '#markup' => $amount_balance . ' ' . $currency_balance_value,
      ];
    }

    return $result;
  }

}
