<?php

namespace Drupal\crm_department_balance;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\crm_payments\PaymentService;
use Drupal\crm_statements\PaymentStatementService;

/**
 * Class DepartmentBalanceService.
 *
 * @package Drupal\crm_department_balance.
 */
class DepartmentBalanceService {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\crm_payments\PaymentService
   */
  protected $paymentService;

  /**
   * Drupal\crm_statements\PaymentStatementService.
   *
   * @var \Drupal\crm_statements\PaymentStatementService
   */
  protected $paymentStatementService;

  /**
   * StaffBirthdaysService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   Drupal\Core\Session\AccountProxy definition.
   * @param \Drupal\crm_payments\PaymentService $paymentService
   *   The payment service.
   * @param \Drupal\crm_statements\PaymentStatementService $statementService
   *   The payment statement service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    AccountProxy $currentUser,
    PaymentService $paymentService,
    PaymentStatementService $statementService) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->currentUser = $currentUser;
    $this->paymentService = $paymentService;
    $this->paymentStatementService = $statementService;
  }

  /**
   * Get Departments balance.
   *
   * @param int $balance_id
   *   Balance id.
   * @param bool $is_admin_department
   *   Query for administrative departments.
   *
   * @return array
   *   Return departments ids.
   */
  public function getDepartmentsInBalance(int $balance_id, bool $is_admin_department): array {
    $department_balance = $this->entityTypeManager->getStorage('node')
      ->load($balance_id);

    if ($is_admin_department) {
      $departments = $department_balance->get('db_admin_departments')
        ->referencedEntities();
    }
    else {
      $departments = $department_balance->get('db_departments')
        ->referencedEntities();
    }

    $departments_terms = [];
    foreach ($departments as $department) {
      $departments_terms[$department->id()] = $department->getName();
    }

    if ($is_admin_department && in_array('administrative_head', $this->currentUser->getRoles())) {
      $departments_ids = $this->paymentService->getHeadAdministrativeDepartmentsIds($this->currentUser->id());
      $departments_terms = array_intersect_key($departments_terms, $departments_ids);
    }

    return $departments_terms;
  }

  /**
   * Get staff by departments.
   *
   * @param int $department_balance_id
   *   Id of the department balance.
   *
   * @return array
   *   Array with balance staff.
   */
  public function getStaff(int $department_balance_id): array {
    $department_balance = $this->entityTypeManager->getStorage('node')
      ->load($department_balance_id);
    $staff = [];
    if (!empty($department_balance->get('db_staff'))) {
      foreach ($department_balance->get('db_staff')->referencedEntities() as $employee) {
        $staff[$employee->id()] = $employee->get('u_applicants_fio')->value;
      }
    }

    return $staff;
  }

  /**
   * Get all currencies.
   *
   * @return array
   *   Return currencies.
   */
  public function getCurrencies(): array {
    $currency_terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree('currency');
    $terms = [];
    foreach ($currency_terms as $currency_term) {
      $terms[$currency_term->tid] = $currency_term->name;
    }

    return $terms;
  }

  /**
   * Get expenses type.
   *
   * @return array
   *   Return expenses type.
   */
  public function getExpenseTypes(): array {
    $expenses_type_terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree('expense_type_transaction');
    $terms = [];
    foreach ($expenses_type_terms as $expenses_type_term) {
      $terms[$expenses_type_term->tid] = $expenses_type_term->name;
    }
    ksort($terms);

    return $terms;
  }

  /**
   * Get Staff settings.
   *
   * @param int $balance_id
   *   Balance id.
   *
   * @return array
   *   Return staff ids.
   */
  public function getStaffSettings(int $balance_id): array {
    $balance_staff = $this->getStaff($balance_id);
    $query = $this->entityTypeManager->getStorage('user')->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('u_is_a_staff', TRUE);
    $query->condition('status', TRUE);
    $query->condition('u_fired', FALSE);
    if ($balance_staff) {
      $query->condition('u_applicants_fio', $balance_staff, 'NOT IN');
    }
    $query->sort('u_applicants_fio', 'ASC');
    $staff_ids = $query->execute();

    $users = $this->entityTypeManager->getStorage('user')
      ->loadMultiple($staff_ids);
    $staff = [];
    foreach ($users as $user) {
      $staff[$user->id()] = $user->get('u_applicants_fio')->value;
    }

    return $staff;
  }

  /**
   * Get Departments settings.
   *
   * @param int $balance_id
   *   Balance id.
   * @param bool $is_admin_department
   *   Query for administrative departments.
   *
   * @return array
   *   Return departments ids.
   */
  public function getDepartmentSettings(int $balance_id, bool $is_admin_department): array {
    $balance_departments = $this->getDepartmentsInBalance($balance_id, $is_admin_department);
    $query = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('vid', 'department');
    $query->condition('is_administrative', $is_admin_department);
    $query->condition('status', TRUE);
    if ($balance_departments) {
      $query->condition('tid', array_keys($balance_departments), 'NOT IN');

    }
    $query->sort('name', 'ASC');
    $departments_ids = $query->execute();

    $terms = [];
    foreach ($departments_ids as $tid) {
      $department = $this->entityTypeManager->getStorage('taxonomy_term')
        ->load($tid);
      if (!empty($department)) {
        $terms[$department->id()] = $department->getName();
      }
    }

    return $terms;
  }

  /**
   * Get Balance transactions.
   *
   * @param int $balance_id
   *   Balance id.
   *
   * @return array
   *   Return Balance transactions.
   */
  public function getBalanceTransactions(int $balance_id) {
    $transactions = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'department_view_details_balance',
      'dvd_balance' => $balance_id,
    ]);

    return $transactions;
  }

  /**
   * Get all department balance.
   */
  public function getAllDepartmentBalance() {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'department_balance')
      ->condition('status', 1)
      ->execute();

    $department_balance_name = [];
    foreach ($query as $department_balance) {
      $department_balance_entity = $this->entityTypeManager->getStorage('node')->load($department_balance);
      $department_balance_name[$department_balance_entity->id()] = $department_balance_entity->get('db_balance_name')->value;
    }
    return $department_balance_name;
  }

  /**
   * Get Department Balances for show on page.
   *
   * @return array
   *   Return Department balance ids.
   */
  public function getBalancesIdForShowUser() {
    $current_user_id = $this->currentUser->id();
    $department_balance_ids = [];
    if (in_array('administrative_head', $this->currentUser->getRoles())) {
      $admin_head_departments_ids = $this->paymentService->getHeadAdministrativeDepartmentsIds($current_user_id);
      if ($admin_head_departments_ids) {
        $query = $this->entityTypeManager->getStorage('node')
          ->getQuery();
        $query->accessCheck(FALSE);
        $query->condition('type', 'department_balance');
        $query->condition('db_admin_departments', $admin_head_departments_ids, 'IN');
        $query->condition('status', TRUE);
        $admin_balance_ids = $query->execute();

        $department_balance_ids += $admin_balance_ids;
      }
    }
    $head_departments_ids = $this->paymentStatementService->getHeadDepartmentsIds($current_user_id);
    if ($head_departments_ids) {
      $query = $this->entityTypeManager->getStorage('node')
        ->getQuery();
      $query->accessCheck(FALSE);
      $query->condition('type', 'department_balance');
      $query->condition('db_departments', $head_departments_ids, 'IN');
      $query->condition('status', TRUE);
      $head_balance_ids = $query->execute();

      $department_balance_ids += $head_balance_ids;
    }

    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('type', 'department_balance');
    $query->condition('db_staff', $current_user_id, 'IN');
    $query->condition('status', TRUE);
    $staff_balance_ids = $query->execute();

    $department_balance_ids += $staff_balance_ids;

    return $department_balance_ids;
  }

  /**
   * Get Balance options.
   *
   * @param int $current_balance
   *   Balance id.
   *
   * @return array
   *   Return Balance options.
   */
  public function getBalanceOptions(int $current_balance) : array {
    $balance_options = [];
    $is_ceo = in_array('ceo', $this->currentUser->getRoles());
    $departments = NULL;
    $balances = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'department_balance',
    ]);
    if (!$is_ceo) {
      $departments = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', 'department')
        ->condition('department_head', $this->currentUser->id())
        ->execute();
    }
    if (!empty($balances)) {
      foreach ($balances as $balance) {
        $balance_departments = $this->getDepartmentsInBalance($balance->id(), FALSE);
        $balance_admin_departments = $this->getDepartmentsInBalance($balance->id(), TRUE);
        $balance_staff = $this->getStaff($balance->id());
        $balance_general_data = $balance_departments + $balance_admin_departments;
        if ($is_ceo || array_intersect_key($departments, $balance_general_data) || array_key_exists($this->currentUser->id(), $balance_staff)) {
          $balance_options[$balance->id()] = $balance->get('db_balance_name')->value;
        }
      }
    }
    unset($balance_options[$current_balance]);

    return $balance_options;
  }

  /**
   * Get Balance names.
   *
   * @param array|null $balance_ids
   *   Balance ids.
   *
   * @return array
   *   Return Balance names.
   */
  public function getBalanceNames($balance_ids = NULL) : array {
    $balance_names = [];
    if (!empty($balance_ids)) {
      foreach ($balance_ids as $balance_id) {
        $balance_entity = $this->entityTypeManager->getStorage('node')->load($balance_id);
        $balance_names[$balance_id] = $balance_entity->get('db_balance_name')->value;
      }
    }

    return $balance_names;
  }

  /**
   * Checking staff for active balances.
   *
   * @param array $employees_ids
   *   Employees ids.
   *
   * @return array
   *   Array with employees with active balances.
   */
  public function checkingStaffForActiveBalances(array $employees_ids) : array {
    $employees = [];
    foreach ($employees_ids as $employee_id) {
      $employee = $this->entityTypeManager->getStorage('user')->load($employee_id);
      $employee_name = $employee->get('name')->value;
      if (!empty($employee->get('u_applicants_fio'))) {
        $employee_name = $employee->get('u_applicants_fio')->value;
      }
      $balance_employee = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'balance_employee')
        ->condition('starting_balance_user_id', $employee_id)
        ->condition('is_active_balance', TRUE)
        ->execute();
      if (count($balance_employee) > 0) {
        $employees[$employee_id] = $employee_name;
      }
    }

    return $employees;
  }

  /**
   * Get all employees with active balance.
   *
   * @return array
   *   Array with all employees with active balance.
   */
  public function getAllEmployeesWithActiveBalance() : array {
    $users_ids = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('u_is_a_staff', 1)
      ->sort('u_applicants_fio', 'ASC')
      ->execute();

    return $this->checkingStaffForActiveBalances($users_ids);
  }

  /**
   * Get all head staff with active balance.
   *
   * @param int $balance_id
   *   Department balance id.
   *
   * @return array
   *   Array with staff ids.
   */
  public function getAllHeadStaffWithActiveBalanceInAttachedDepartments(int $balance_id) : array {
    $staff = [];
    $departments_in_balance = [];
    $balance_departments_ids = [];
    $admin_departments = [];
    $departments = [];

    $department_balance = $this->entityTypeManager->getStorage('node')
      ->load($balance_id);

    if (!empty($department_balance->get('db_admin_departments'))) {
      $admin_departments = $department_balance->get('db_admin_departments')->referencedEntities();
    }
    if (!empty($department_balance->get('db_departments'))) {
      $departments = $department_balance->get('db_departments')->referencedEntities();
    }

    foreach ($admin_departments as $admin_department) {
      $departments_in_balance[$admin_department->id()] = $admin_department->id();
    }

    foreach ($departments as $department) {
      $departments_in_balance[$department->id()] = $department->id();
    }

    $departments_ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->condition('vid', 'department')
      ->condition('department_head', $this->currentUser->id())
      ->execute();

    foreach ($departments_ids as $department_id) {
      if (in_array($department_id, $departments_in_balance)) {
        $balance_departments_ids[$department_id] = $department_id;
      }
    }

    $query = $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('u_is_a_staff', TRUE);
    if (!empty($balance_departments_ids)) {
      $condition_group = $query->orConditionGroup();
      $condition_group->condition('u_department', $balance_departments_ids, 'IN');
      $condition_group->condition('u_executive_department', $balance_departments_ids, 'IN');
      $query->condition($condition_group);
    }
    $query->sort('u_applicants_fio');

    $staff = $query->execute();
    if (!in_array('administrative_head', $this->currentUser->getRoles())) {
      unset($staff[$this->currentUser->id()]);
    }

    return $this->checkingStaffForActiveBalances($staff);
  }

  /**
   * Check if employee has only one attached balance.
   *
   * @param int $balance_id
   *   Company balance id.
   *
   * @return bool
   *   Is employee has only one balance.
   */
  public function checkIfEmployeeHasOnlyOneAttachedBalance(int $balance_id) : bool {
    $employee_departments = $this->paymentStatementService->getHeadDepartmentsIds($this->currentUser->id());
    $employee_administrative_departments = $this->paymentService->getHeadAdministrativeDepartmentsIds($this->currentUser->id());
    $balances_query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'department_balance');
    $condition_group = $balances_query->orConditionGroup();
    if (count($employee_departments) > 0) {
      $condition_group->condition('db_departments', $employee_departments, 'IN');
    }
    if (count($employee_administrative_departments) > 0) {
      $condition_group->condition('db_admin_departments', $employee_administrative_departments, 'IN');
    }
    $condition_group->condition('db_staff', $this->currentUser->id());
    $balances_query->condition($condition_group);
    $balances_ids = $balances_query->execute();
    $key_to_delete = array_search($balance_id, $balances_ids);
    unset($balances_ids[$key_to_delete]);

    return count($balances_ids) == 0;
  }

  /**
   * Get all departments by head id.
   */
  public function getAllDepartmentsByHeadId($employee_id) : array {

    return $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'department')
      ->condition('department_head', $employee_id)
      ->condition('status', 1)
      ->execute();
  }

}
