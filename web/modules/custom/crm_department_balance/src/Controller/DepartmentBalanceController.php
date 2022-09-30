<?php

namespace Drupal\crm_department_balance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element\Form;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\crm_department_balance\DepartmentBalanceService;
use Drupal\crm_department_balance\Form\AddExpenseTransactionForm;
use Drupal\crm_department_balance\Form\AdminDepartmentSettingsForm;
use Drupal\crm_department_balance\Form\DepartmentSettingsForm;
use Drupal\crm_department_balance\Form\EditDepartmentBalanceForm;
use Drupal\crm_department_balance\Form\AddIncomeTransactionForm;
use Drupal\crm_department_balance\Form\EditDepartmentTransactionForm;
use Drupal\crm_department_balance\Form\RemoveAdminDepartmentForm;
use Drupal\crm_department_balance\Form\RemoveDepartmentBalanceForm;
use Drupal\crm_department_balance\Form\RemoveDepartmentForm;
use Drupal\crm_department_balance\Form\RemoveStaffForm;
use Drupal\crm_department_balance\Form\RemoveDepartmentTransactionForm;
use Drupal\crm_department_balance\Form\StaffSettingsForm;
use Drupal\crm_statements\PaymentStatementService;
use Drupal\hr_common\Service\UserService;
use Drupal\sales_module\Component\Utility\HtmlExtra;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * CRM Department balance.
 */
class DepartmentBalanceController extends ControllerBase {

  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The User Service.
   *
   * @var \Drupal\hr_common\Service\UserService
   */
  protected $userService;

  /**
   * Drupal\crm_statements\PaymentStatementService.
   *
   * @var \Drupal\crm_statements\PaymentStatementService
   */
  protected $paymentStatementService;

  /**
   * Drupal\crm_department_balance\DepartmentBalanceService.
   *
   * @var \Drupal\crm_department_balance\DepartmentBalanceService
   */
  protected $departmentBalanceService;

  /**
   * Constructs a DepartmentBalanceController object.
   *
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Account Proxy Interface.
   * @param \Drupal\hr_common\Service\UserService $user_service
   *   User service.
   * @param \Drupal\crm_statements\PaymentStatementService $statementService
   *   The payment statement service.
   * @param \Drupal\crm_department_balance\DepartmentBalanceService $department_balance_service
   *   The department balance service.
   */
  public function __construct(
    Renderer $renderer,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    UserService $user_service,
    PaymentStatementService $statementService,
    DepartmentBalanceService $department_balance_service) {
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->userService = $user_service;
    $this->paymentStatementService = $statementService;
    $this->departmentBalanceService = $department_balance_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('hr_common.user_service'),
      $container->get('crm_statements.statement_service'),
      $container->get('crm_department_balance.department_balance_service'),
    );
  }

  /**
   * ShowDepartmentBalance.
   *
   * @return array
   *   Render array.
   */
  public function showDepartmentBalance(): array {
    $is_ceo = FALSE;
    if (in_array('ceo', $this->currentUser->getRoles())) {
      $is_ceo = TRUE;
    }
    if ($is_ceo) {
      $view = views_embed_view('department_balance', 'company_balance');
    }
    else {
      $view = views_embed_view('department_balance', 'department_balance_head');
      $balance_ids = $this->departmentBalanceService->getBalancesIdForShowUser();
      if ($balance_ids) {
        $balance_ids_str = implode(',', $balance_ids);
        $args = [
          'id' => $balance_ids_str,
        ];
        $view["#arguments"] = $args;
      }
    }

    $block = $this->renderer->renderRoot($view);
    $url_add_balance_object = Url::fromRoute('crm_department_balance.head_department_balance');
    $url_add_balance = $url_add_balance_object->toString();
    return [
      '#theme' => 'department_balance',
      '#block' => $block,
      '#is_ceo' => $is_ceo,
      '#url_add_balance' => $url_add_balance,
    ];
  }

  /**
   * AddDepartmentBalance.
   */
  public function addDepartmentBalance() {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.department_balance');
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm('Drupal\crm_department_balance\Form\AddDepartmentBalanceForm');
  }

  /**
   * DeleteDepartmentBalance.
   */
  public function deleteDepartmentBalance($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.department_balance');
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(RemoveDepartmentBalanceForm::class, $nid);
  }

  /**
   * Show details balance.
   *
   * @param int|string $nid
   *   Id of the current department balance node.
   *
   * @return array
   *   Render array.
   */
  public function showDetailsBalance($nid): array {
    $department_balance_name = 'Undefined balance';
    $is_ceo = FALSE;
    $total_balance = 0;
    $department_balance = $this->entityTypeManager->getStorage('node')
      ->load($nid);
    if (in_array('ceo', $this->currentUser->getRoles())) {
      $is_ceo = TRUE;
    }
    if (!empty($department_balance->get('db_balance_name'))) {
      $department_balance_name = $department_balance->get('db_balance_name')->value;
    }
    $departments_names = '';
    if (!empty($department_balance->get('db_departments'))) {
      $departments_names = implode(', ', $this->departmentBalanceService->getDepartmentsInBalance($nid, FALSE));
    }
    $departments = $department_balance->get('db_admin_departments')
      ->referencedEntities();
    $departments_terms = [];
    foreach ($departments as $department) {
      $departments_terms[] = $department->getName();
    }
    $admin_departments_names = '';
    if (!empty($departments_terms)) {
      $admin_departments_names = implode(', ', $departments_terms);
    }
    $staff_names = '';
    if (!empty($department_balance->get('db_staff'))) {
      $staff_names = implode(', ', $this->departmentBalanceService->getStaff($nid));
    }
    if (!empty($department_balance->get('db_amount'))) {
      $total_balance = $department_balance->get('db_amount')->value;
    }
    $currency_balance_value = '';
    if ($department_balance->get('db_currency')->target_id) {
      $currency_balance_id = $department_balance->get('db_currency')->target_id;
      $currency_balance = $this->entityTypeManager->getStorage('taxonomy_term')
        ->load($currency_balance_id);
      $currency_balance_value = $currency_balance->getName();
    }
    $view = views_embed_view('department_balance_details', 'balance_details');
    $render_view = $this->renderer->renderRoot($view);
    $url_add_income_transaction_object = Url::fromRoute('crm_department_balance.add_income_transaction', [
      'nid' => $nid,
    ]);
    $url_add_income_transaction = $url_add_income_transaction_object->toString();
    $url_add_expense_transaction_object = Url::fromRoute('crm_department_balance.add_expense_transaction', [
      'nid' => $nid,
    ]);
    $url_add_expense_transaction = $url_add_expense_transaction_object->toString();
    $url_balance_setting_object = Url::fromRoute('crm_department_balance.settings_balance', [
      'nid' => $nid,
    ]);
    $url_balance_setting = $url_balance_setting_object->toString();

    return [
      '#theme' => 'department_balance_details',
      '#balance_name' => $department_balance_name,
      '#admin_departments_names' => $admin_departments_names,
      '#departments_names' => $departments_names,
      '#staff_names' => $staff_names,
      '#total_balance' => $total_balance,
      '#currency_balance_value' => $currency_balance_value,
      '#is_ceo' => $is_ceo,
      '#render_view' => $render_view,
      '#url_add_income_transaction' => $url_add_income_transaction,
      '#url_add_expense_transaction' => $url_add_expense_transaction,
      '#url_balance_setting' => $url_balance_setting,
    ];
  }

  /**
   * SettingsBalance.
   */
  public function settingsBalance($nid) {
    $department_balance = $this->entityTypeManager->getStorage('node')
      ->load($nid);
    $department_balance_title = $department_balance->getTitle();
    $view_admin_departments = views_embed_view('department_balance_settings', 'admin_department_balance');
    $block_admin_departments = $this->renderer->renderRoot($view_admin_departments);
    $view_departments = views_embed_view('department_balance_settings', 'departments_balance');
    $block_departments = $this->renderer->renderRoot($view_departments);
    $view_staff = views_embed_view('department_balance_settings', 'staff_balance');
    $block_staff = $this->renderer->renderRoot($view_staff);
    $url_add_admin_department_object = Url::fromRoute('crm_department_balance.admin_department_settings', [
      'nid' => $nid,
    ]);
    $url_add_admin_department = $url_add_admin_department_object->toString();
    $url_add_department_object = Url::fromRoute('crm_department_balance.department_settings', [
      'nid' => $nid,
    ]);
    $url_add_department = $url_add_department_object->toString();
    $url_add_staff_object = Url::fromRoute('crm_department_balance.staff_settings', [
      'nid' => $nid,
    ]);
    $url_add_staff = $url_add_staff_object->toString();
    $url_view_details = Url::fromRoute('crm_department_balance.view_details_balance', [
      'nid' => $nid,
    ]);
    $url_view_details = $url_view_details->toString();

    return [
      '#theme' => 'balance_setting',
      '#block_admin_departments' => $block_admin_departments,
      '#block_departments' => $block_departments,
      '#block_staff' => $block_staff,
      '#department_balance_title' => $department_balance_title,
      '#url_add_admin_department' => $url_add_admin_department,
      '#url_add_department' => $url_add_department,
      '#url_add_staff' => $url_add_staff,
      '#url_view_details' => $url_view_details,
    ];
  }

  /**
   * Add income transaction form.
   */
  public function addIncomeTransaction($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.view_details_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(AddIncomeTransactionForm::class, $nid);
  }

  /**
   * EditDepartmentBalance.
   */
  public function editDepartmentBalance($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.department_balance');
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(EditDepartmentBalanceForm::class, $nid);
  }

  /**
   * EditDepartmentTransaction.
   */
  public function editDepartmentTransaction($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.view_details_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }

    return $this->formBuilder()
      ->getForm(EditDepartmentTransactionForm::class, $nid);
  }

  /**
   * Remove Department Transaction.
   */
  public function removeDepartmentTransaction(int $nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.view_details_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }

    return $this->formBuilder()
      ->getForm(RemoveDepartmentTransactionForm::class, $nid);
  }

  /**
   * Add expense transaction form.
   */
  public function addExpenseTransaction($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.view_details_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(AddExpenseTransactionForm::class, $nid);
  }

  /**
   * Remove admin department form.
   */
  public function removeAdminDepartmentBalance($nid, $target_id) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.settings_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(RemoveAdminDepartmentForm::class, $nid, $target_id);
  }

  /**
   * Remove department form.
   */
  public function removeDepartmentBalance($nid, $target_id) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.settings_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(RemoveDepartmentForm::class, $nid, $target_id);
  }

  /**
   * Remove staff form.
   */
  public function removeStaffBalance($nid, $uid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.settings_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(RemoveStaffForm::class, $nid, $uid);
  }

  /**
   * Admin department settings form.
   */
  public function adminDepartmentSettings($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.settings_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(AdminDepartmentSettingsForm::class, $nid);
  }

  /**
   * Department settings form.
   */
  public function departmentSettings($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.settings_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(DepartmentSettingsForm::class, $nid);
  }

  /**
   * Staff settings form.
   */
  public function staffSettings($nid) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_department_balance.settings_balance', [
        'nid' => $nid,
      ]);
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()
      ->getForm(StaffSettingsForm::class, $nid);
  }

}
