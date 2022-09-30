<?php

namespace Drupal\crm_department_balance\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\crm_department_balance\DepartmentBalanceService;

/**
 * Determines access to for block add pages.
 */
class TransactionAccessCheck implements AccessInterface {


  /**
   * Drupal\crm_department_balance\DepartmentBalanceService.
   *
   * @var \Drupal\crm_department_balance\DepartmentBalanceService
   */
  protected $departmentBalanceService;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a DepartmentBalanceController object.
   *
   * @param \Drupal\crm_department_balance\DepartmentBalanceService $department_balance_service
   *   The department balance service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    DepartmentBalanceService $department_balance_service,
    EntityTypeManagerInterface $entityTypeManager) {
    $this->departmentBalanceService = $department_balance_service;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Checks access to the block add page for the block type.
   */
  public function access(AccountInterface $account, $nid = NULL) {
    $department_transaction_entity = $this->entityTypeManager->getStorage('node')
      ->load($nid);
    $balance_id = $department_transaction_entity->get('dvd_balance')->target_id;
    $users_balances = $this->departmentBalanceService->getBalancesIdForShowUser();
    $is_ceo = FALSE;
    if (in_array('ceo', $account->getRoles())) {
      $is_ceo = TRUE;
    }

    if (in_array($balance_id, $users_balances) || $is_ceo) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
