<?php

namespace Drupal\crm_department_balance\Plugin\views\field;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\crm_department_balance\Enum\ExpenseTypes;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a custom field for Department Balance.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("department_balance_transaction_actions")
 */
class DepartmentBalanceTransactionActions extends FieldPluginBase {

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
    $is_system_transaction = FALSE;
    if (!empty($values->_entity->get('dvd_is_system_transaction')->value)) {
      $is_system_transaction = $values->_entity->get('dvd_is_system_transaction')->value;
    }
    $transaction_author_id = $values->_entity->get('uid')->target_id;

    $expense_type = '';
    if (!empty($values->_entity->get('dvd_expense_type')->target_id)) {
      $expense_type = $values->_entity->get('dvd_expense_type')->entity->machine_name->value;
    }

    // If this transaction had crated another transaction.
    $had_created_another_transaction = in_array($expense_type, [
      ExpenseTypes::TRANSFER_TO_ANOTHER_BALANCE,
      ExpenseTypes::EMPLOYEE_MONEY_TRANSFER,
    ]);

    $has_rights_to_edit = in_array('ceo', $this->currentUser->getRoles()) || $transaction_author_id === $this->currentUser->id();

    $result['edit'] = [
      '#type' => 'markup',
      '#markup' => '<div class="disabled btn btn-xs btn-warning fa fa-pencil-square-o round-b-size mg-l_5 mg-r_5"></div>',
    ];

    $result['remove'] = [
      '#type' => 'markup',
      '#markup' => '<div class="disabled btn btn-xs btn-danger fa fa-trash round-b-size mg-l_5 mg-r_5"></div>',
    ];

    if (!empty($nid) && !$is_system_transaction && $has_rights_to_edit) {
      $result['edit'] = [
        '#type' => 'link',
        '#title' => '',
        '#url' => Url::fromRoute('crm_department_balance.edit_department_transaction', [
          'nid' => $nid,
        ]),
        '#attributes' => [
          'class' => 'use-ajax btn btn-xs btn-warning fa fa-pencil-square-o round-b-size mg-l_5 mg-r_5',
          'data-dialog-type' => 'modal',
        ],
      ];

      if (!$had_created_another_transaction) {
        $result['remove'] = [
          '#type' => 'link',
          '#title' => '',
          '#url' => Url::fromRoute('crm_department_balance.remove_transaction', [
            'nid' => $nid,
          ]),
          '#attributes' => [
            'class' => 'use-ajax btn btn-xs btn-danger fa fa-trash round-b-size mg-l_5 mg-r_5',
            '#title' => 'Remove',
            'data-dialog-type' => 'modal',
          ],
        ];
      }
    }

    return $result;
  }

}
