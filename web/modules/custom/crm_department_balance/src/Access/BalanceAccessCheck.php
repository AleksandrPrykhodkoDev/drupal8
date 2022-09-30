<?php

namespace Drupal\crm_department_balance\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\crm_department_balance\DepartmentBalanceService;

/**
 * Determines access to for block add pages.
 */
class BalanceAccessCheck implements AccessInterface {


  /**
   * Drupal\crm_department_balance\DepartmentBalanceService.
   *
   * @var \Drupal\crm_department_balance\DepartmentBalanceService
   */
  protected $departmentBalanceService;

  /**
   * Constructs a DepartmentBalanceController object.
   *
   * @param \Drupal\crm_department_balance\DepartmentBalanceService $department_balance_service
   *   The department balance service.
   */
  public function __construct(
    DepartmentBalanceService $department_balance_service) {
    $this->departmentBalanceService = $department_balance_service;
  }

  /**
   * Checks access to the block add page for the block type.
   */
  public function access(AccountInterface $account, $nid = NULL) {
    $users_balances = $this->departmentBalanceService->getBalancesIdForShowUser();
    $is_ceo = FALSE;
    if (in_array('ceo', $account->getRoles())) {
      $is_ceo = TRUE;
    }

    if (in_array($nid, $users_balances) || $is_ceo) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
