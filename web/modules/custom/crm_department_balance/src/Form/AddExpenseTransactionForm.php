<?php

namespace Drupal\crm_department_balance\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\hr_common\Ajax\CloseFormCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddExpenseTransactionForm.
 */
class AddExpenseTransactionForm extends FormBase {

  /**
   * Machine name of Expense type transaction term.
   */
  const EMPLOYEE_MONEY_TRANSFER = 'employee_money_transfer';

  /**
   * Machine name of Expense type transaction term.
   */
  const TRANSFER_TO_ANOTHER_BALANCE = 'transfer_to_another_balance';

  /**
   * Machine name of usd Currency term.
   */
  const USD = 'dollars';

  /**
   * The id of an admin user.
   */
  const ADMIN_ID = 1;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service instance.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Action Log Service.
   *
   * @var \Drupal\crm_action_log\ActionLogService
   */
  protected $actionLog;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    $instance->actionLog = $container->get('crm_action_log.log');
    $instance->currentUser = $container->get('current_user');
    $instance->paymentStatementService = $container->get('crm_statements.statement_service');
    $instance->departmentBalanceService = $container->get('crm_department_balance.department_balance_service');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_income_transaction';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    $department_balance = $this->paymentStatementService->getPaymentStatementById($nid);
    $balance_currency_id = $department_balance->get('db_currency')->target_id;
    $current_date = new DrupalDateTime('now', 'UTC');
    $date = $current_date->format('Y-m-d');
    $is_ceo = FALSE;
    $has_admin_departments = FALSE;
    $has_departments = FALSE;
    $has_staff = FALSE;
    $default_admin_department_ceo = FALSE;
    $default_department_ceo = FALSE;
    $default_employee_ceo = FALSE;
    $default_admin_department = FALSE;
    $default_department = FALSE;
    $default_employee = [];
    $admin_departments_for_head = [];
    $departments_for_head = [];
    $term_transfer_to_another_balance = taxonomy_term_machine_name_load(self::TRANSFER_TO_ANOTHER_BALANCE, 'expense_type_transaction')->id();
    $term_employee_money_transfer = taxonomy_term_machine_name_load(self::EMPLOYEE_MONEY_TRANSFER, 'expense_type_transaction')->id();
    $is_not_head = FALSE;
    $is_only_one_balance = FALSE;
    $user_input = $form_state->getUserInput();

    if (in_array('ceo', $this->currentUser->getRoles())) {
      $is_ceo = TRUE;
    }

    if (!$is_ceo) {
      $is_only_one_balance = $this->departmentBalanceService->checkIfEmployeeHasOnlyOneAttachedBalance($nid);
      $all_head_departments = $this->departmentBalanceService->getAllDepartmentsByHeadId($this->currentUser->id());
      if (count($all_head_departments) == 0) {
        $is_not_head = TRUE;
      }
    }

    // Here we get values about admin departments for visibility of data.
    if ($department_balance->get('db_admin_departments')->referencedEntities()) {
      $has_admin_departments = TRUE;
      if (!$is_ceo) {
        foreach ($department_balance->get('db_admin_departments')->referencedEntities() as $department) {
          if ($this->currentUser->id() === $department->get('department_head')->target_id) {
            $admin_departments_for_head[$department->id()] = $department->getName();
          }
        }
      }
      if (count($department_balance->get('db_admin_departments')->referencedEntities()) < 2) {
        $default_admin_department_ceo = $department_balance->get('db_admin_departments')->target_id;
      }
    }

    // Here we get values about departments for visibility of data.
    if ($department_balance->get('db_departments')->referencedEntities()) {
      $has_departments = TRUE;
      if (!$is_ceo) {
        foreach ($department_balance->get('db_departments')->referencedEntities() as $department) {
          if ($this->currentUser->id() === $department->get('department_head')->target_id) {
            $departments_for_head[$department->id()] = $department->getName();
          }
        }
      }
      if (count($department_balance->get('db_departments')->referencedEntities()) < 2) {
        $default_department_ceo = $department_balance->get('db_departments')->target_id;
      }
    }

    // Here we get values about staff for visibility of data.
    if ($department_balance->get('db_staff')->referencedEntities()) {
      $has_staff = TRUE;
      if (!$is_ceo) {
        foreach ($department_balance->get('db_staff')->referencedEntities() as $employee) {
          if ($employee->id() === $this->currentUser->id()) {
            $user_name = $employee->get('name')->value;
            if (!empty($employee->get('u_applicants_fio'))) {
              $user_name = $employee->get('u_applicants_fio')->value;
            }
            $default_employee[$employee->id()] = $user_name;
          }
        }
      }
      if (count($department_balance->get('db_staff')->referencedEntities()) < 2) {
        $default_employee_ceo = $department_balance->get('db_staff')->target_id;
      }
    }

    if (count($this->departmentBalanceService->getDepartmentsInBalance($nid, TRUE)) === 1) {
      $default_admin_department = array_key_first($this->departmentBalanceService->getDepartmentsInBalance($nid, TRUE));
    }

    if (count($departments_for_head) === 1) {
      $default_department = array_key_first($departments_for_head);
    }

    $form['balance_id'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['date'] = [
      '#type' => 'date',
      '#description' => $this->t('You should use next format of date: dd.mm.yyyy'),
      '#title' => $this->t('Transaction date'),
      '#default_value' => $date,
      '#required' => TRUE,
    ];

    // Here we add a new checkbox only for CEO.
    if ($is_ceo) {
      $form['from_another_person'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Transaction from another person'),
      ];
    }

    $expense_types = $this->departmentBalanceService->getExpenseTypes();
    if ($is_only_one_balance) {
      unset($expense_types[$term_transfer_to_another_balance]);
    }
    if ($is_not_head) {
      unset($expense_types[$term_employee_money_transfer]);
    }

    $form['expense_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Expense option'),
      '#options' => ['' => '-None-'] + $expense_types,
      '#ajax' => [
        'callback' => '::expenseTypeCallback',
        'event' => 'change',
        'wrapper' => 'expense-type-ajax',
      ],
    ];

    $form['select_employee'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Employee'),
      '#states' => [
        'visible' => [
          ':input[name="expense_type"]' => ['value' => $term_employee_money_transfer],
        ],
      ],
      '#ajax' => [
        'callback' => '::anotherBalanceCallback',
        'event' => 'change',
        'wrapper' => 'another-balance-ajax',
      ],
    ];

    if ($is_ceo) {
      $form['select_employee']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getAllEmployeesWithActiveBalance();
    }
    if (!$is_not_head && !$is_ceo) {
      $form['select_employee']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getAllHeadStaffWithActiveBalanceInAttachedDepartments($nid);
    }

    $form['select_balance'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Balance'),
      '#options' => ['' => '-None-'] + $this->departmentBalanceService->getBalanceOptions($nid),
      '#states' => [
        'visible' => [
          ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
        ],
      ],
      '#ajax' => [
        'callback' => '::anotherBalanceCallback',
        'event' => 'change',
        'wrapper' => 'another-balance-ajax',
      ],
      '#suffix' => '<div class="exchange-rate-select">',
    ];

    $form['rate_wrapper']['sum'] = [
      '#type' => 'textfield',
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#title' => $this->t('Amount of payment'),
      '#required' => TRUE,
    ];

    $form['rate_wrapper']['sum_currency'] = [
      '#prefix' => '<div id="expense-type-ajax">',
      '#type' => 'select',
      '#title' => $this->t('Transaction currency'),
      '#options' => $this->departmentBalanceService->getCurrencies(),
      '#default_value' => $balance_currency_id,
      '#states' => [
        'disabled' => [
          ':input[name="expense_type"]' => [
            ['value' => $term_transfer_to_another_balance],
            ['value' => $term_employee_money_transfer],
          ],
        ],
      ],
      '#suffix' => '</div></div>',
    ];

    if ($user_input['expense_type'] == $term_transfer_to_another_balance || $user_input['expense_type'] == $term_employee_money_transfer) {
      $form['rate_wrapper']['sum_currency']['#title'] = $this->t('Current balance currency');
      $form['rate_wrapper']['sum_currency']['#value'] = $balance_currency_id;
      $form['rate_wrapper']['sum_currency']['#disabled'] = TRUE;
    }

    $usd_id = taxonomy_term_machine_name_load(self::USD, 'currency')->id();
    $default_another_balance_currency = NULL;

    if (!empty($user_input['select_balance'])) {
      $another_balance = $this->entityTypeManager->getStorage('node')->load($user_input['select_balance']);
      $default_another_balance_currency = $another_balance->get('db_currency')->target_id;
    }
    if ($user_input['expense_type'] == $term_employee_money_transfer) {
      $default_another_balance_currency = $usd_id;
    }

    $form['another_balance_rate'] = [
      '#prefix' => '<div id="another-balance-ajax"><div id="another-balance-wrapper">',
      '#type' => 'textfield',
      '#title' => $this->t('Exchange Rate'),
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#states' => [
        'visible' => [
          [
            ':input[name="expense_type"]' => ['value' => $term_employee_money_transfer],
            ':input[name="sum_currency"]' => ['!value' => $usd_id],
          ],
          'or',
          [
            ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
          ],
        ],
      ],
    ];

    $form['another_balance_currency'] = [
      '#disabled' => TRUE,
      '#title' => $this->t('Transaction currency'),
      '#type' => 'select',
      '#options' => $this->departmentBalanceService->getCurrencies(),
      '#states' => [
        'visible' => [
          [
            ':input[name="expense_type"]' => ['value' => $term_employee_money_transfer],
            ':input[name="sum_currency"]' => ['!value' => $usd_id],
          ],
          'or',
          [
            ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
          ],
        ],
      ],
      '#value' => $default_another_balance_currency,
      '#suffix' => '</div>',
    ];

    $form['description_another_rate'] = [
      '#prefix' => '<div class="description-exchange-rate">',
      '#type' => 'item',
      '#markup' => $this->t('<p>You must enter the exchange rate of the balance currency to the transaction currency.</p>'),
      '#states' => [
        'visible' => [
          [
            ':input[name="expense_type"]' => ['value' => $term_employee_money_transfer],
            ':input[name="sum_currency"]' => ['!value' => $usd_id],
          ],
          'or',
          [
            ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
          ],
        ],
      ],
      '#suffix' => '</div></div>',
    ];

    if ($default_another_balance_currency === $balance_currency_id) {
      $form['another_balance_rate']['#type'] = 'hidden';
      $form['another_balance_currency']['#type'] = 'hidden';
      $form['description_another_rate']['#type'] = 'hidden';
    }

    if (array_key_exists($balance_currency_id, $this->departmentBalanceService->getCurrencies())) {
      $currency = $balance_currency_id;
    };

    $exchange_rate_invisible_state = [
      [':input[name="sum_currency"]' => ['value' => $currency]],
      'or',
      [':input[name="expense_type"]' => ['value' => $term_employee_money_transfer]],
      'or',
      [':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance]],
    ];

    $form['rate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exchange Rate'),
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#states' => [
        'invisible' => $exchange_rate_invisible_state,
      ],
    ];

    $form['description_rate'] = [
      '#prefix' => '<div class="description-exchange-rate">',
      '#type' => 'item',
      '#markup' => $this->t('<p>You must enter the exchange rate of the balance currency to the transaction currency.</p>'),
      '#states' => [
        'invisible' => $exchange_rate_invisible_state,
      ],
      '#suffix' => '</div>',
    ];

    if ($is_ceo && !$has_admin_departments && !$has_staff && !$has_departments) {
      $form['from_another_person']['#disabled'] = TRUE;
    }

    $form['options'] = [
      '#type' => 'radios',
      '#title' => $this->t('Expense from'),
      '#states' => [
        'invisible' => [
          ':input[name="from_another_person"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Here conditions to view radio options about administrative departments.
    if ($is_ceo && $has_admin_departments) {
      $form['options']['#options']['admin_department'] = $this->t('Administrative Department');
    }
    if (!$is_ceo && count($admin_departments_for_head) > 0) {
      $form['options']['#options']['admin_department'] = $this->t('Administrative Department');
      $form['options']['#required'] = TRUE;
    }

    // Here conditions to view radio options about departments.
    if ($is_ceo && $has_departments) {
      $form['options']['#options']['department'] = $this->t('Department');
    }
    if (!$is_ceo && count($departments_for_head) > 0) {
      $form['options']['#options']['department'] = $this->t('Department');
      $form['options']['#required'] = TRUE;
    }

    // Here conditions to view radio options about staff for ceo or personal use for other people.
    if ($is_ceo && $has_staff) {
      $form['options']['#options']['staff'] = $this->t('Staff');
    }
    if (!$is_ceo && count($default_employee) > 0) {
      $form['options']['#options']['staff'] = $this->t('Personal use');
      $form['options']['#required'] = TRUE;
    }

    // Here conditions to set default value for department option.
    if ($has_departments && !$has_admin_departments && !$has_staff) {
      $form['options']['#default_value'] = 'department';
    }
    if (!$is_ceo && count($admin_departments_for_head) === 0 && count($default_employee) === 0 && $has_departments) {
      $form['options']['#default_value'] = 'department';
    }

    // Here conditions to set default value for administrative department option.
    if ($is_ceo && !$has_departments && $has_admin_departments && !$has_staff) {
      $form['options']['#default_value'] = 'admin_department';
    }
    if (!$is_ceo && count($departments_for_head) === 0 && $has_admin_departments && count($default_employee) === 0) {
      $form['options']['#default_value'] = 'admin_department';
    }

    // Here conditions to set default value for staff option.
    if ($is_ceo && !$has_departments && !$has_admin_departments && $has_staff) {
      $form['options']['#default_value'] = 'staff';
    }
    if (!$is_ceo && count($default_employee) > 0 && !$departments_for_head && !$admin_departments_for_head) {
      $form['options']['#default_value'] = 'staff';
    }

    $form['admin_departments'] = [
      '#type' => 'select',
      '#title' => $this->t('Administrative Department'),
      '#description' => $this->t('Select a department to link it to the balance'),
    ];

    // Ceo can see all balance administrative departments in select.
    if ($is_ceo) {
      $form['admin_departments']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getDepartmentsInBalance($nid, TRUE);
    }
    // Head can see only those balance administrative departments in select where head is admin department head.
    else {
      $form['admin_departments']['#options'] = ['' => '-None-'] + $admin_departments_for_head;
    }

    // Set default administrative department in select for ceo.
    if ($default_admin_department_ceo) {
      $form['admin_departments']['#default_value'][$default_admin_department_ceo] = $default_admin_department_ceo;
    }
    // Set default administrative department in select for head.
    if ($default_admin_department) {
      $form['admin_departments']['#default_value'][$default_admin_department] = $default_admin_department;
    }

    $form['departments'] = [
      '#type' => 'select',
      '#title' => $this->t('Department'),
      '#description' => $this->t('Select a department to link it to the balance'),
    ];

    // Ceo can see all balance departments in select.
    if ($is_ceo) {
      $form['departments']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getDepartmentsInBalance($nid, FALSE);
    }
    // Head can see only those balance departments in select where head is department head.
    else {
      $form['departments']['#options'] = ['' => '-None-'] + $departments_for_head;
    }

    // Set default department in select for ceo.
    if ($default_department_ceo) {
      $form['departments']['#default_value'][$default_department_ceo] = $default_department_ceo;
    }
    // Set default department in select for head.
    if ($default_department) {
      $form['departments']['#default_value'][$default_department] = $default_department;
    }

    $form['staff'] = [
      '#type' => 'select',
      '#title' => $this->t('Employee'),
      '#description' => $this->t('Select an employee to link it to the balance'),
    ];

    // CEO can see all balance staff in staff select.
    if ($is_ceo) {
      $form['staff']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getStaff($nid);
    }
    // Employee can see only yourself in staff select.
    else {
      $form['staff']['#options'] = ['' => '-None-'] + $default_employee;
    }

    // Set default employee in staff select.
    if ($default_employee_ceo) {
      $form['staff']['#default_value'][$default_employee_ceo] = $default_employee_ceo;
    }

    if (count($default_employee) > 0) {
      $form['staff']['#default_value'][array_key_first($default_employee)] = array_key_first($default_employee);
    }

    // Set requirements of visibility for selects.
    if ($is_ceo) {
      $form['admin_departments']['#states'] = [
        'visible' => [
          ':input[name="options"]' => ['value' => 'admin_department'],
          ':input[name="from_another_person"]' => ['checked' => TRUE],
        ],
      ];
      $form['departments']['#states'] = [
        'visible' => [
          ':input[name="options"]' => ['value' => 'department'],
          ':input[name="from_another_person"]' => ['checked' => TRUE],
        ],
      ];
      $form['staff']['#states'] = [
        'visible' => [
          ':input[name="options"]' => ['value' => 'staff'],
          ':input[name="from_another_person"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $form['admin_departments']['#states'] = [
        'visible' => [
          ':input[name="options"]' => ['value' => 'admin_department'],
        ],
      ];
      $form['departments']['#states'] = [
        'visible' => [
          ':input[name="options"]' => ['value' => 'department'],
        ],
      ];
      $form['staff']['#states'] = [
        'visible' => [
          ':input[name="options"]' => ['value' => 'staff'],
        ],
      ];
    }

    $form['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Transaction description'),
      '#rows' => 4,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      '#suffix' => '</div>',
    ];
    // Attach the inline messages library.
    $form['#attached']['library'] = [
      'hr_common/close_popup',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $department_balance_entity = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('balance_id'));
    $department_balance_id = $department_balance_entity->get('db_currency')->target_id;
    $amount = $form_state->getValue('sum');
    $currency = $form_state->getValue('sum_currency');
    $rate = $form_state->getValue('rate');
    $options = $form_state->getValue('options');
    $transaction_from_another_person = FALSE;
    if ($form_state->getValue('from_another_person')) {
      $transaction_from_another_person = TRUE;
    }

    if ($transaction_from_another_person && $options === 'admin_department' && empty($form_state->getValue('admin_departments'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('Administrative Department field is empty!')
      );
    }
    if ($transaction_from_another_person && $options === 'department' && empty($form_state->getValue('departments'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('Department field is empty!')
      );
    }
    if ($transaction_from_another_person && $options === 'staff' && empty($form_state->getValue('staff'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('Staff field is empty!')
      );
    }

    if ($currency != $department_balance_id) {
      if (empty($rate)) {
        $form_state->setErrorByName(
          'add_statement_transaction', $this->t('The Exchange Rate field is empty!')
        );
      }
    }

    if (is_string($rate) && str_contains($rate, ',')) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Exchange Rate: The fractional mark must be a point')
      );
    }

    if (!empty($rate) && !is_numeric($rate)) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Exchange Rate field data is not a number!')
      );
    }

    if (empty($amount)) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Amount of payment field is empty!')
      );
    }
    if (is_string($amount) && str_contains($amount, ',')) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The fractional mark must be a point(.)')
      );
    }
    if (!is_numeric($amount)) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Amount field data is not a number!')
      );
    }
    if ($amount < 0) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('You can only enter positive numbers!')
      );
    }
    if (empty($form_state->getValue('expense_type'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Select Expense option field is empty!')
      );
    }
    if ($rate < 0) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('You can only enter positive numbers!')
      );
    }
    $term_transfer_to_another_balance = taxonomy_term_machine_name_load(self::TRANSFER_TO_ANOTHER_BALANCE, 'expense_type_transaction')->id();
    if ($form_state->getValue('expense_type') == $term_transfer_to_another_balance && empty($form_state->getValue('select_balance'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Select Balance field is empty!')
      );
    }
    $term_employee_money_transfer = taxonomy_term_machine_name_load(self::EMPLOYEE_MONEY_TRANSFER, 'expense_type_transaction')->id();
    if ($form_state->getValue('expense_type') == $term_employee_money_transfer && empty($form_state->getValue('select_employee'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Select Employee field is empty!')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $balance_id = $form_state->getValue('balance_id');
    $amount = round($form_state->getValue('sum'), 2);
    $exchange_rates = $form_state->getValue('rate');
    $transaction_expense_type = $form_state->getValue('expense_type');
    $term_transfer_to_another_balance = taxonomy_term_machine_name_load(self::TRANSFER_TO_ANOTHER_BALANCE, 'expense_type_transaction')->id();
    $term_employee_money_transfer = taxonomy_term_machine_name_load(self::EMPLOYEE_MONEY_TRANSFER, 'expense_type_transaction')->id();
    if ($exchange_rates) {
      $amount = round($form_state->getValue('sum') / $exchange_rates, 2);
    }
    $department_balance = $this->entityTypeManager->getStorage('node')->load($balance_id);
    $amount_department_balance = '';

    if ($department_balance->get('db_amount')->value) {
      $amount_department_balance = $department_balance->get('db_amount')->value;
    }
    $new_amount_department_balance = (float) $amount_department_balance - $amount;
    $balance_transaction = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'department_view_details_balance',
      'title' => 'Department transaction for ' . $balance_id,
      'dvd_amount_of_payment' => $amount,
      'dvd_balance' => $balance_id,
      'dvd_date' => $form_state->getValue('date'),
      'dvd_is_income' => FALSE,
      'dvd_amount_in_input_currency' => round($form_state->getValue('sum'), 2),
      'dvd_transaction_currency' => $form_state->getValue('sum_currency'),
      'dvd_old_balance' => $amount_department_balance,
      'dvd_expense_type' => $transaction_expense_type,
      'dvd_transaction_rate' => $exchange_rates,
    ]);
    $another_balance_transaction_description = $this->t('Expense from Balance: @balance', [
      '@balance' => $department_balance->get('db_balance_name')->value,
    ]);
    if ((in_array('ceo', $this->currentUser->getRoles()) && $form_state->getValue('from_another_person') === 1) || !in_array('ceo', $this->currentUser->getRoles())) {
      if ($form_state->getValue('options') === 'admin_department' && !empty($form_state->getValue('admin_departments'))) {
        $balance_transaction->set('dvd_admin_departments', $form_state->getValue('admin_departments'));
        $admin_department = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_state->getValue('admin_departments'));
        $admin_department_name = $admin_department->get('name')->value;
        $another_balance_transaction_description = $this->t('Transaction from @balance<p>Expense from Administrative Department: @department</p>', [
          '@balance' => $department_balance->get('db_balance_name')->value,
          '@department' => $admin_department_name,
        ]);
      }
      if ($form_state->getValue('options') === 'department' && !empty($form_state->getValue('departments'))) {
        $balance_transaction->set('dvd_departments', $form_state->getValue('departments'));
        $department = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_state->getValue('departments'));
        $department_name = $department->get('name')->value;
        $another_balance_transaction_description = $this->t('Transaction from @balance<p>Expense from Department: @department</p>', [
          '@balance' => $department_balance->get('db_balance_name')->value,
          '@department' => $department_name,
        ]);
      }
      if ($form_state->getValue('options') === 'staff' && !empty($form_state->getValue('staff'))) {
        $balance_transaction->set('dvd_staff', $form_state->getValue('staff'));
        $employee = $this->entityTypeManager->getStorage('user')->load($form_state->getValue('staff'));
        $employee_name = $employee->get('name')->value;
        if (!empty($employee->get('u_applicants_fio'))) {
          $employee_name = $employee->get('u_applicants_fio')->value;
        }
        $another_balance_transaction_description = $this->t('Transaction from @balance<p>Expense from Employee: @employee</p>', [
          '@balance' => $department_balance->get('db_balance_name')->value,
          '@employee' => $employee_name,
        ]);
      }
    }

    if ($form_state->getValue('expense_type') === $term_transfer_to_another_balance) {
      $another_balance_id = $form_state->getValue('select_balance');
      $another_balance_obj = $this->entityTypeManager->getStorage('node')->load($another_balance_id);

      $transaction_description = $this->t('@comment<p>Transfer to another balance: @balance</p>', [
        '@balance' => $another_balance_obj->get('title')->value,
        '@comment' => $form_state->getValue('comment'),
      ]);

      $another_amount_department_balance = '';
      if ($another_balance_obj->get('db_amount')->value) {
        $another_amount_department_balance = $another_balance_obj->get('db_amount')->value;
      }
      $another_balance_rate = 1;
      if ((float) $form_state->getValue('another_balance_rate') > 0) {
        $another_balance_rate = $form_state->getValue('another_balance_rate');
      }
      $new_amount_by_exchange_rate = round($form_state->getValue('sum') * $another_balance_rate, 2);
      $new_another_amount_department_balance = (float) $another_amount_department_balance + $new_amount_by_exchange_rate;
      $another_balance_transaction = $this->entityTypeManager->getStorage('node')->create([
        'uid' => self::ADMIN_ID,
        'type' => 'department_view_details_balance',
        'title' => 'Department transaction for ' . $another_balance_id,
        'dvd_amount_of_payment' => $new_amount_by_exchange_rate,
        'dvd_balance' => $another_balance_id,
        'dvd_date' => $form_state->getValue('date'),
        'dvd_is_income' => TRUE,
        'dvd_transaction_rate' => $exchange_rates,
        'dvd_is_system_transaction' => TRUE,
        'dvd_old_balance' => $another_amount_department_balance,
        'dvd_amount_in_input_currency' => round($form_state->getValue('sum'), 2),
        'dvd_transaction_currency' => $form_state->getValue('sum_currency'),
        'dvd_transaction_description' => $another_balance_transaction_description,
      ]);
      $another_balance_transaction->save();
      $another_balance_obj->set('db_amount', round($new_another_amount_department_balance, 2));
      $another_balance_obj->save();
      $balance_transaction->set('dvd_another_balance', $another_balance_id);
      $balance_transaction->set('dvd_another_balance_rate', $another_balance_rate);
    }

    if ($form_state->getValue('expense_type') === $term_employee_money_transfer) {
      $employee_id = $form_state->getValue('select_employee');
      $employee = $this->entityTypeManager->getStorage('user')->load($employee_id);
      $employee_fio = $employee->get('name')->value;
      if (!empty($employee->get('u_applicants_fio'))) {
        $employee_fio = $employee->get('u_applicants_fio')->value;
      }
      $transaction_description = $this->t('@comment<p>Employee money transfer: @employee</p>', [
        '@employee' => $employee_fio,
        '@comment' => $form_state->getValue('comment'),
      ]);
      $amount = $form_state->getValue('sum');
      if ((float) $form_state->getValue('another_balance_rate') > 0) {
        $amount = round($amount * $form_state->getValue('another_balance_rate'), 2);
      }
      $employee_balances = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => 'balance_employee',
        'starting_balance_user_id' => $employee_id,
      ]);
      $employee_balance = array_shift($employee_balances);

      $old_starting_balance = $employee_balance->get('starting_balance')->value;
      $new_starting_balance = round($old_starting_balance - $amount, 2);

      $employee_balance->set('starting_balance', $new_starting_balance);
      $employee_balance->save();

      $statement_date = new DrupalDateTime($form_state->getValue('date'), 'UTC');
      $statement_date_format = $statement_date->format('Y-m-d');
      $statement_description = $this->t('<p><strong>Create by - System transaction</strong></p><p>@comment</p>', [
        '@comment' => $another_balance_transaction_description,
      ]);
      $employee_statement = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'payment_statement',
        'title' => 'Payment statement for ' . $employee_fio,
        'uid' => 1,
        'ps_amount' => $amount,
        'ps_balance' => $employee_balance->id(),
        'ps_comment' => $statement_description,
        'ps_date' => $statement_date_format,
        'ps_employee' => $employee_id,
        'ps_is_payroll' => FALSE,
        'ps_old_balance' => $old_starting_balance,
        'ps_new_balance' => $new_starting_balance,
        'ps_is_payments_transaction' => TRUE,
      ]);
      $employee_statement->save();
      $balance_transaction->set('dvd_another_employee', $employee_id);
    }

    $comment = $form_state->getValue('comment');
    if (!empty($transaction_description)) {
      $comment = $transaction_description;
    }

    $balance_transaction->set('dvd_transaction_description', $comment);
    $balance_transaction->save();
    $log_message = $this->t('Expense transaction - @transaction_name was added to Department Balance - @balance_name', [
      '@transaction_name' => 'Department transaction for ' . $department_balance->get('db_balance_name')->value,
      '@balance_name' => $department_balance->get('db_balance_name')->name,
    ]);
    $this->actionLog->log('add_transaction_to_department_balance', $log_message, $this->currentUser->id());
    $department_balance->set('db_amount', round($new_amount_department_balance, 2));
    $department_balance->save();

    $this->messenger()
      ->addStatus($this->t('Expense transaction was successfully added'));
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $message = [
      '#theme' => 'status_messages',
      '#message_list' => $this->messenger()->all(),
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'error' => $this->t('Error message'),
        'warning' => $this->t('Warning message'),
      ],
    ];
    $messages = $this->renderer->render($message);

    // If errors exists return new form.
    $errors = $this->messenger()->messagesByType('error');
    $ajax_response->addCommand(new HtmlCommand('#form-system-messages', $messages));

    if (empty($errors)) {
      $ajax_response->addCommand(new CloseFormCommand(1000));
      $ajax_response->addCommand(new HtmlCommand('.hide-form', ''));
      $parent_url = getRequestParentUrl();
      if ($parent_url) {
        $ajax_response->addCommand(new RedirectCommand($parent_url));
      }
    }
    $this->messenger()->deleteAll();

    return $ajax_response;
  }

  /**
   * Another balance currency callback.
   *
   * @param array $form
   *   Form array.
   *
   * @return array
   *   Form array.
   */
  public function anotherBalanceCallback(array &$form) : array {
    return [
      $form['another_balance_currency'],
      $form['another_balance_rate'],
      $form['description_another_rate'],
    ];
  }

  /**
   * Expense type field callback.
   *
   * @param array $form
   *   Form array.
   *
   * @return array
   *   Form array.
   */
  public function expenseTypeCallback(array &$form) {
    return $form['rate_wrapper']['sum_currency'];
  }

}
