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
 * @ViewsField("department_balance_details_balance_history")
 */
class ViewDetailsBalanceHistoryField extends FieldPluginBase {

  /**
   * The entity type manager service instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
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
    $nid = $values->nid;
    $is_income = FALSE;
    $department_transaction = $this->entityTypeManager->getStorage('node')->load($nid);

    $old_balance = $department_transaction->get('dvd_old_balance')->value ?? 0;
    $new_total_balance = $old_balance - $department_transaction->get('dvd_amount_of_payment')->value;
    if ($department_transaction->get('dvd_is_income')->value) {
      $is_income = TRUE;
      $new_total_balance = $old_balance + $department_transaction->get('dvd_amount_of_payment')->value;
    }
    $data_for_field = $this->t('@old_total_balance @operator @amount = @new_total_balance', [
      '@old_total_balance' => $old_balance,
      '@operator' => ($is_income) ? '+' : '-',
      '@amount' => $department_transaction->get('dvd_amount_of_payment')->value,
      '@new_total_balance' => $new_total_balance,
    ]);

    return [
      '#markup' => $data_for_field,
    ];
  }

}
