<?php

namespace Drupal\crm_department_balance\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Render\Markup;
use Drupal\hr_common\Ajax\CloseFormCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EditDepartmentTransactionForm.
 */
class EditDepartmentTransactionForm extends FormBase {

  /**
   * Machine name of Expense type transaction term.
   */
  const EMPLOYEE_MONEY_TRANSFER = 'employee_money_transfer';

  /**
   * Machine name of Expense type transaction term.
   */
  const TRANSFER_TO_ANOTHER_BALANCE = 'transfer_to_another_balance';


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
    $department_transaction_entity = $this->entityTypeManager->getStorage('node')
      ->load($nid);
    $department_transaction_date = $department_transaction_entity->get('dvd_date')->value;
    $department_transaction_amount = $department_transaction_entity->get('dvd_amount_in_input_currency')->value;
    $department_transaction_comment = $department_transaction_entity->get('dvd_transaction_description')->value;
    $department_transaction_currency = $department_transaction_entity->get('dvd_transaction_currency')->target_id;
    $department_transaction_department_balance = $department_transaction_entity->get('dvd_balance')->target_id;
    $department_transaction_rate = $department_transaction_entity->get('dvd_transaction_rate')->value;
    $department_transaction_is_income = $department_transaction_entity->get('dvd_is_income')->value;
    $department_transaction_staff = NULL;
    $term_transfer_to_another_balance = taxonomy_term_machine_name_load(self::TRANSFER_TO_ANOTHER_BALANCE, 'expense_type_transaction')->id();
    $department_transaction_type = $department_transaction_entity->get('dvd_expense_type')->target_id;
    $is_transfer_to_another_balance = FALSE;
    $is_employee_money_transfer = FALSE;
    if ($department_transaction_type === $term_transfer_to_another_balance) {
      $is_transfer_to_another_balance = TRUE;
    }
    $term_employee_money_transfer = taxonomy_term_machine_name_load(self::EMPLOYEE_MONEY_TRANSFER, 'expense_type_transaction')->id();
    if ($department_transaction_type === $term_employee_money_transfer) {
      $is_employee_money_transfer = TRUE;
    }
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
    $default_option = FALSE;

    if (!empty($department_transaction_entity->get('dvd_admin_departments')->target_id)) {
      $default_option = 'admin_department';
      $default_admin_department_ceo = $department_transaction_entity->get('dvd_admin_departments')->target_id;
    }
    if (!empty($department_transaction_entity->get('dvd_departments')->target_id)) {
      $default_option = 'department';
    }
    if (!empty($department_transaction_entity->get('dvd_staff')->target_id)) {
      $department_transaction_staff = $department_transaction_entity->get('dvd_staff')->target_id;
      $default_option = 'staff';
    }

    $form['transaction_id'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['title'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h3 class="unit-type-title">Edit @name transaction</h3>', [
        '@name' => $department_transaction_is_income ? 'income' : 'expense',
      ]),
    ];

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['date'] = [
      '#type' => 'date',
      '#description' => $this->t('You should use next format of date: dd.mm.yyyy'),
      '#title' => $this->t('Date'),
      '#default_value' => $department_transaction_date,
      '#required' => TRUE,
    ];

    if (!$department_transaction_is_income) {

      // Getting necessary expense types for 'expense_type' options.
      $expense_types = $this->departmentBalanceService->getExpenseTypes();
      if (!$is_employee_money_transfer) {
        unset($expense_types[$term_employee_money_transfer]);
      }
      if (!$is_transfer_to_another_balance) {
        unset($expense_types[$term_transfer_to_another_balance]);
      }

      $form['expense_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Expense option'),
        '#options' => ['' => '-None-'] + $expense_types,
        '#default_value' => $department_transaction_entity->get('dvd_expense_type')->target_id,
      ];
    }

    if ($is_transfer_to_another_balance || $is_employee_money_transfer) {
      $form['expense_type']['#disabled'] = TRUE;
    }

    if ($is_employee_money_transfer) {
      $form['select_employee'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Employee'),
        '#options' => ['' => '-None-'] + $this->departmentBalanceService->getAllEmployeesWithActiveBalance(),
        '#states' => [
          'visible' => [
            ':input[name="expense_type"]' => ['value' => $term_employee_money_transfer],
          ],
        ],
        '#disabled' => TRUE,
        '#default_value' => $department_transaction_entity->get('dvd_another_employee')->target_id,
      ];
    }

    if (!$is_transfer_to_another_balance) {
      $form['select_employee']['#suffix'] = '<div class="exchange-rate-select">';
    }

    if ($is_transfer_to_another_balance) {
      $form['select_balance'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Balance'),
        '#options' => ['' => '-None-'] + $this->departmentBalanceService->getBalanceOptions($nid),
        '#states' => [
          'visible' => [
            ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
          ],
        ],
        '#disabled' => TRUE,
        '#default_value' => $department_transaction_entity->get('dvd_another_balance')->target_id,
        '#suffix' => '<div class="exchange-rate-select">',
      ];
    }

    $form['rate_wrapper']['sum'] = [
      '#type' => 'textfield',
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#title' => $this->t('Amount'),
      '#default_value' => $department_transaction_amount,
      '#required' => TRUE,
      '#states' => [
        'disabled' => [
          ':input[name="expense_type"]' => [
            'values' => [
              $term_employee_money_transfer,
              $term_transfer_to_another_balance,
            ],
          ],
        ],
      ],
    ];

    $form['rate_wrapper']['sum_currency'] = [
      '#type' => 'select',
      '#options' => $this->departmentBalanceService->getCurrencies(),
      '#default_value' => $department_transaction_currency,
      '#states' => [
        'disabled' => [
          ':input[name="expense_type"]' => [
            'values' => [
              $term_employee_money_transfer,
              $term_transfer_to_another_balance,
            ],
          ],
        ],
      ],
      '#suffix' => '</div>',
    ];

    if ($is_transfer_to_another_balance) {
      $form['transfer_to_another_balance_wrapper'] = [
        '#suffix' => '<div class="exchange-rate-select">',
      ];

      $form['another_balance_rate'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Exchange Rate'),
        '#description' => $this->t('You should use number, e.g 2.56'),
        '#states' => [
          'visible' => [
            ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
          ],
        ],
        '#default_value' => $department_transaction_entity->get('dvd_another_balance_rate')->value,
        '#disabled' => TRUE,
      ];

      if (!$department_transaction_is_income) {
        $another_balance_id = $department_transaction_entity->get('dvd_another_balance')->target_id;
        $another_balance = $this->entityTypeManager->getStorage('node')->load($another_balance_id);
        if (!empty($another_balance->get('db_currency'))) {
          $another_balance_currency = $another_balance->get('db_currency')->target_id;
        }
      }

      $form['another_balance_currency'] = [
        '#disabled' => TRUE,
        '#type' => 'select',
        '#options' => $this->departmentBalanceService->getCurrencies(),
        '#states' => [
          'visible' => [
            ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
          ],
        ],
        '#default_value' => $another_balance_currency,
      ];

      $form['transfer_to_another_balance_end_wrapper'] = [
        '#suffix' => '</div>',
      ];

      $form['description_rate'] = [
        '#prefix' => '<div class="description-exchange-rate">',
        '#type' => 'item',
        '#markup' => $this->t('<p>You must enter the exchange rate of the balance currency to the transaction currency.</p>'),
        '#states' => [
          'visible' => [
            ':input[name="expense_type"]' => ['value' => $term_transfer_to_another_balance],
          ],
        ],
        '#suffix' => '</div>',
      ];
    }

    if ($department_transaction_entity->get('dvd_another_balance_rate')->value == 1) {
      $form['another_balance_rate']['#type'] = 'hidden';
      $form['another_balance_currency']['#type'] = 'hidden';
      $form['description_rate']['#type'] = 'hidden';
    }

    $department_balance = $this->entityTypeManager->getStorage('node')->load($department_transaction_department_balance);
    $balance_currency = NULL;
    if (!empty($department_balance) && !empty($department_balance->get('db_currency'))) {
      $balance_currency = $department_balance->get('db_currency')->target_id;
    }

    $form['rate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exchange Rate'),
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#default_value' => $department_transaction_rate,
      '#states' => [
        'invisible' => [
          ':input[name="sum_currency"]' => ['value' => $balance_currency],
        ],
        'disabled' => [
          ':input[name="expense_type"]' => ['value' => $term_employee_money_transfer],
        ],
      ],
    ];

    $form['description_another_rate'] = [
      '#prefix' => '<div class="description-exchange-rate">',
      '#type' => 'item',
      '#markup' => $this->t('<p>You must enter the exchange rate of the balance currency to the transaction currency.</p>'),
      '#states' => [
        'invisible' => [
          ':input[name="sum_currency"]' => ['value' => $balance_currency],
        ],
      ],
      '#suffix' => '</div>',
    ];

    if (in_array('ceo', $this->currentUser->getRoles())) {
      $is_ceo = TRUE;
    }

    // Here we add a new checkbox only for CEO.
    if ($is_ceo) {
      $form['from_another_person'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Transaction from another person'),
        '#disabled' => TRUE,
      ];
    }
    if ($is_ceo && $default_option) {
      $form['from_another_person']['#default_value'] = 1;
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

    if (count($this->departmentBalanceService->getDepartmentsInBalance($department_transaction_department_balance, TRUE)) === 1) {
      $default_admin_department = array_key_first($this->departmentBalanceService->getDepartmentsInBalance($department_transaction_department_balance, TRUE));
    }

    if (count($departments_for_head) === 1) {
      $default_department = array_key_first($departments_for_head);
    }

    $form['options'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select option'),
      '#default_value' => $default_option,
      '#required' => FALSE,
      '#states' => [
        'disabled' => [
          ':input[name="expense_type"]' => [
            'values' => [
              $term_employee_money_transfer,
              $term_transfer_to_another_balance,
            ],
          ],
        ],
      ],
    ];

    // Ceo can't see the options if checkbox wasn't checked.
    if ($is_ceo) {
      $form['options']['#states']['invisible'] = [
        ':input[name="from_another_person"]' => ['checked' => FALSE],
      ];
    }

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

    $form['admin_departments'] = [
      '#type' => 'select',
      '#title' => $this->t('Administrative Department'),
      '#description' => $this->t('Select a department to link it to the balance'),
    ];

    // Ceo can see all balance administrative departments in select.
    if ($is_ceo) {
      $form['admin_departments']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getDepartmentsInBalance($department_transaction_department_balance, TRUE);
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
      $form['departments']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getDepartmentsInBalance($department_transaction_department_balance, FALSE);
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
      $form['staff']['#options'] = ['' => '-None-'] + $this->departmentBalanceService->getStaff($department_transaction_department_balance);
    }
    // Employee can see only yourself in staff select.
    else {
      $form['staff']['#options'] = ['' => '-None-'] + $default_employee;
    }

    // Set default employee in staff select.
    if ($department_transaction_staff) {
      $form['staff']['#default_value'][$default_employee_ceo] = $department_transaction_staff;
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

    if ($is_transfer_to_another_balance || $is_employee_money_transfer) {
      $form['options']['#disabled'] = TRUE;
      $form['rate_wrapper']['sum']['#disabled'] = TRUE;
      $form['rate_wrapper']['sum_currency']['#disabled'] = TRUE;
      $form['expense_type']['#disabled'] = TRUE;
      $form['admin_departments']['#disabled'] = TRUE;
      $form['departments']['#disabled'] = TRUE;
      $form['staff']['#disabled'] = TRUE;
    }

    $form['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Transaction description'),
      '#default_value' => strip_tags($department_transaction_comment),
      '#rows' => 4,
      '#required' => TRUE,
    ];

    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Reason for change'),
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
    $department_transaction_entity = $this->entityTypeManager->getStorage('node')
      ->load($form_state->getValue('transaction_id'));
    $department_transaction_id = $department_transaction_entity->get('dvd_transaction_currency')->target_id;
    $amount = $form_state->getValue('sum');
    $currency = $form_state->getValue('sum_currency');
    $rate = $form_state->getValue('rate');
    $options = $form_state->getValue('options');
    $is_income = FALSE;
    if (!empty($department_transaction_entity->get('dvd_is_income')->value)) {
      $is_income = $department_transaction_entity->get('dvd_is_income')->value;
    }

    if ($options === 'admin_department' && empty($form_state->getValue('admin_departments'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('Administrative Department field is empty!')
      );
    }
    if ($options === 'department' && empty($form_state->getValue('departments'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('Department field is empty!')
      );
    }
    if ($options === 'staff' && empty($form_state->getValue('staff'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('Staff field is empty!')
      );
    }

    if ($currency != $department_transaction_id) {
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
    if ($rate < 0) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('You can only enter positive numbers!')
      );
    }
    if (!$is_income && empty($form_state->getValue('expense_type'))) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('The Expense type field is empty!')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $term_transfer_to_another_balance = taxonomy_term_machine_name_load('transfer_to_another_balance', 'expense_type_transaction')->id();
    $department_transaction_entity = $this->entityTypeManager->getStorage('node')
      ->load($form_state->getValue('transaction_id'));
    $department_transaction_type = $department_transaction_entity->get('dvd_expense_type')->target_id;
    $is_transfer_to_another_balance = FALSE;
    if ($department_transaction_type === $term_transfer_to_another_balance) {
      $is_transfer_to_another_balance = TRUE;
    }
    $term_employee_money_transfer = taxonomy_term_machine_name_load(self::EMPLOYEE_MONEY_TRANSFER, 'expense_type_transaction')->id();
    $department_transaction_department_balance_id = $department_transaction_entity->get('dvd_balance')->target_id;
    $transaction_type = 'Income';
    $department_balance = $this->entityTypeManager->getStorage('node')->load($department_transaction_department_balance_id);
    $balance_currency = NULL;
    if (!empty($department_balance) && !empty($department_balance->get('db_currency'))) {
      $balance_currency = $department_balance->get('db_currency')->target_id;
    }

    if ($form_state->getValue('expense_type')) {
      $transaction_expense_type = $form_state->getValue('expense_type');
      $department_transaction_entity->set('dvd_expense_type', $transaction_expense_type);
      $transaction_type = 'Expense';
    }

    $exchange_rates = '';
    if ($form_state->getValue('sum_currency') !== $balance_currency) {
      $exchange_rates = $form_state->getValue('rate');
      $department_transaction_entity->set('dvd_transaction_rate', $exchange_rates);
    }

    $amount = round($form_state->getValue('sum'), 2);
    if ($exchange_rates) {
      $amount = round($form_state->getValue('sum') / $exchange_rates, 2);
    }
    $department_transaction_department_balance = $this->entityTypeManager->getStorage('node')
      ->load($department_transaction_department_balance_id);
    $amount_balance = '';
    if ($department_transaction_department_balance->get('db_amount')->value) {
      $amount_balance = $department_transaction_department_balance->get('db_amount')->value;
    }

    if ($department_transaction_entity->get('dvd_is_income')->value) {
      $update_department_balance = $amount_balance - $department_transaction_entity->get('dvd_amount_of_payment')->value + $amount;
    }
    else {
      $update_department_balance = $amount_balance + $department_transaction_entity->get('dvd_amount_of_payment')->value - $amount;
    }
    $date_of_change_obj = new DrupalDateTime('now', 'UTC');
    $date_of_change_format = $date_of_change_obj->format('d-m-Y');
    $old_comment = $department_transaction_entity->get('dvd_transaction_description')->value;
    $new_comment = !empty($form_state->getValue('comment')) ? $form_state->getValue('comment') : $old_comment;
    $author_id = $this->currentUser->id();
    $author = $this->entityTypeManager->getStorage('user')->load($author_id);
    $author_name = $author->get('name')->value;
    if (!empty($author->get('u_applicants_fio'))) {
      $author_name = $author->get('u_applicants_fio')->value;
    }
    $old_amount = $department_transaction_entity->get('dvd_amount_of_payment')->value;
    $currency_id = $department_transaction_entity->get('dvd_transaction_currency')->target_id;
    $currency_name = NULL;
    if (!empty($currency_id)) {
      $currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($currency_id);
      $currency_name = $currency->get('name')->value;
    }
    $old_date = $department_transaction_entity->get('dvd_date')->value;
    $old_date_obj = new DrupalDateTime($old_date);
    $old_date_format = $old_date_obj->format('d-m-Y');
    $new_date_obj = new DrupalDateTime($form_state->getValue('date'));
    $new_date_format = $new_date_obj->format('d-m-Y');
    $rate = 1;
    if ($exchange_rates) {
      $rate = $exchange_rates;
    }
    $transaction_currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_state->getValue('sum_currency'));
    $transaction_currency_title = $transaction_currency->get('name')->value;
    if (!empty($balance_currency)) {
      $currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($balance_currency);
      $balance_currency_title = $currency->get('name')->value;
    }
    $calculation = $this->t('@amount@currency / @rate = @result@balance_currency', [
      '@amount' => $form_state->getValue('sum'),
      '@rate' => $rate,
      '@result' => round($form_state->getValue('sum') / $rate, 2),
      '@currency' => $transaction_currency_title,
      '@balance_currency' => $balance_currency_title,
    ]);
    $transaction_description = $this->t('@old_comment</p><hr/><p>edited on</p><p>Comment: @new_comment.</p></br><p>Date: @old_date edited on @new_date</p><p>Calculation: @calculation</p><p>Amount: @old_amount@balance_currency edited on @new_amount@balance_currency</p></p><p>Date of change: @date.</p><p>Reason for change: @reason.</p><p>Author of changes: @author</p>', [
      '@old_comment' => Markup::create($old_comment),
      '@new_comment' => $new_comment,
      '@date' => $date_of_change_format,
      '@reason' => $form_state->getValue('reason'),
      '@author' => $author_name,
      '@old_amount' => $old_amount,
      '@new_amount' => $amount,
      '@currency' => $currency_name,
      '@old_date' => $old_date_format,
      '@new_date' => $new_date_format,
      '@calculation' => $calculation,
      '@balance_currency' => $balance_currency_title,
    ]);

    $department_transaction_entity->set('dvd_amount_of_payment', $amount);
    if ($is_transfer_to_another_balance) {
      $amount = round($form['rate_wrapper']['sum']['#default_value'] * $form['another_balance_rate']['#default_value'], 2);
      $another_balance_transactions = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => 'department_view_details_balance',
        'dvd_date' => $old_date,
        'dvd_balance' => $department_transaction_entity->get('dvd_another_balance')->target_id,
        'dvd_amount_of_payment' => $amount,
        'dvd_is_income' => 1,
      ]);
      $another_balance_transaction = array_shift($another_balance_transactions);
      if ($new_date_obj->diff($old_date_obj)->invert === 1) {
        $another_balance_transaction_description = $another_balance_transaction->get('dvd_transaction_description')->value . 'Date has been changed from ' . $old_date_format . ' to ' . $new_date_format;
        $another_balance_transaction->set('dvd_date', $form_state->getValue('date'));
        $another_balance_transaction->set('dvd_transaction_description', $another_balance_transaction_description);
      }
      $another_balance_transaction->save();
    }

    if ($department_transaction_type === $term_employee_money_transfer) {
      $employee_balances = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'balance_employee')
        ->condition('starting_balance_user_id', $form['select_employee']['#default_value'])
        ->execute();
      $employee_balance = array_shift($employee_balances);

      $employee_statements = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => 'payment_statement',
        'ps_amount' => $amount,
        'ps_balance' => $employee_balance,
        'ps_is_payments_transaction' => TRUE,
        'ps_is_payroll' => FALSE,
        'ps_date' => $old_date,
      ]);
      $employee_statement = array_shift($employee_statements);

      $old_date_obj = new DrupalDateTime($old_date);
      $old_date_format = $old_date_obj->format('d-m-Y');
      $new_date_obj = new DrupalDateTime($form_state->getValue('date'));
      $new_date_format = $new_date_obj->format('d-m-Y');
      if ($new_date_obj->diff($old_date_obj)->invert === 1) {
        $statement_transaction_description = $employee_statement->get('ps_comment')->value . 'Date has been changed from ' . $old_date_format . ' to ' . $new_date_format;
        $employee_statement->set('ps_date', $form_state->getValue('date'));
        $employee_statement->set('ps_comment', $statement_transaction_description);
      }
      $employee_statement->save();
    }

    $department_transaction_entity->set('dvd_date', $form_state->getValue('date'));
    $department_transaction_entity->set('dvd_amount_in_input_currency', $form_state->getValue('sum'));
    $department_transaction_entity->set('dvd_transaction_currency', $form_state->getValue('sum_currency'));
    $department_transaction_entity->set('dvd_transaction_description', $transaction_description);
    if ((in_array('ceo', $this->currentUser->getRoles()) && $form_state->getValue('from_another_person') === 1) || !in_array('ceo', $this->currentUser->getRoles())) {
      if ($form_state->getValue('options') === 'admin_department' && !empty($form_state->getValue('admin_departments'))) {
        $department_transaction_entity->set('dvd_admin_departments', $form_state->getValue('admin_departments'));
        $department_transaction_entity->set('dvd_departments', NULL);
        $department_transaction_entity->set('dvd_staff', NULL);
      }
      if ($form_state->getValue('options') === 'department' && !empty($form_state->getValue('departments'))) {
        $department_transaction_entity->set('dvd_departments', $form_state->getValue('departments'));
        $department_transaction_entity->set('dvd_admin_departments', NULL);
        $department_transaction_entity->set('dvd_staff', NULL);
      }
      if ($form_state->getValue('options') === 'staff' && !empty($form_state->getValue('staff'))) {
        $department_transaction_entity->set('dvd_staff', $form_state->getValue('staff'));
        $department_transaction_entity->set('dvd_admin_departments', NULL);
        $department_transaction_entity->set('dvd_departments', NULL);
      }
    }

    $current_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $user_fio = '';
    if (!empty($current_user->get('u_applicants_fio')->value)) {
      $user_fio = $current_user->get('u_applicants_fio')->value;
    }
    $log_message = $this->t('@transaction_type Transaction - @transaction_name on Department Balance - @balance_name was updated by @user_name', [
      '@transaction_type' => $transaction_type,
      '@transaction_name' => $department_transaction_entity->get('title')->value,
      '@balance_name' => $department_transaction_department_balance->get('db_balance_name')->value,
      '@user_name' => $user_fio,
    ]);
    $this->actionLog->log('edit_transaction_on_department_balance', $log_message, $this->currentUser->id());
    $department_transaction_entity->save();
    $department_transaction_department_balance->set('db_amount', $update_department_balance);
    $department_transaction_department_balance->save();
    $this->messenger()
      ->addStatus($this->t('Transaction was successfully updated'));
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

}
