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
 * Class AddIncomeTransactionForm.
 */
class AddIncomeTransactionForm extends FormBase {

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
    if (in_array('ceo', $this->currentUser->getRoles())) {
      $is_ceo = TRUE;
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
      '#title' => $this->t('Transaction Date'),
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

    $form['rate_wrapper']['sum'] = [
      '#prefix' => '<div class="exchange-rate-select">',
      '#type' => 'textfield',
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#title' => $this->t('Amount of payment'),
      '#required' => TRUE,
    ];

    $form['rate_wrapper']['sum_currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Transaction currency'),
      '#options' => $this->departmentBalanceService->getCurrencies(),
      '#default_value' => $balance_currency_id,
      '#suffix' => '</div>',
    ];

    if (array_key_exists($balance_currency_id, $this->departmentBalanceService->getCurrencies())) {
      $currency = $balance_currency_id;
    };

    $form['rate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exchange Rate'),
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#states' => [
        'invisible' => [
          ':input[name="sum_currency"]' => ['value' => $currency],
        ],
      ],
    ];

    $form['description_rate'] = [
      '#prefix' => '<div class="description-exchange-rate">',
      '#type' => 'item',
      '#markup' => $this->t('<p>You must enter the exchange rate of the balance currency to the transaction currency.</p>'),
      '#states' => [
        'invisible' => [
          ':input[name="sum_currency"]' => ['value' => $currency],
        ],
      ],
      '#suffix' => '</div>',
    ];

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

    if ($is_ceo && !$has_admin_departments && !$has_staff && !$has_departments) {
      $form['from_another_person']['#disabled'] = TRUE;
    }

    $form['options'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select option'),
      '#required' => FALSE,
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

    if (preg_match('/[0-9]{5}/', $form_state->getValue('date')) !== 0) {
      $form_state->setErrorByName('add_exchange_rate', $this->t('You should enter the correct year!'));
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
    if ($rate < 0) {
      $form_state->setErrorByName(
        'add_statement_transaction', $this->t('You can only enter positive numbers!')
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
    if ($exchange_rates) {
      $amount = round($form_state->getValue('sum') / $exchange_rates, 2);
    }
    $department_balance = $this->entityTypeManager->getStorage('node')->load($balance_id);
    $amount_department_balance = '';
    if ($department_balance->get('db_amount')->value) {
      $amount_department_balance = $department_balance->get('db_amount')->value;
    }
    $new_amount_department_balance = (float) $amount_department_balance + $amount;
    $rate = 1;
    if ($exchange_rates) {
      $rate = $exchange_rates;
    }
    $balance_currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($department_balance->get('db_currency')->target_id);
    if (!empty($balance_currency)) {
      $balance_currency_title = $balance_currency->get('name')->value;
    }
    $transaction_currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_state->getValue('sum_currency'));
    $transaction_currency_title = $transaction_currency->get('name')->value;
    $calculation = $this->t('@amount@currency / @rate = @result@balance_currency', [
      '@amount' => $form_state->getValue('sum'),
      '@rate' => $rate,
      '@result' => round($form_state->getValue('sum') / $rate, 2),
      '@currency' => $transaction_currency_title,
      '@balance_currency' => $balance_currency_title,
    ]);
    $description = $this->t('@comment<p>Calculation: @calculation</p>', [
      '@comment' => $form_state->getValue('comment'),
      '@calculation' => $calculation,
    ]);
    $balance_transaction = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'department_view_details_balance',
      'title' => 'Department transaction for ' . $balance_id,
      'dvd_amount_of_payment' => $amount,
      'dvd_balance' => $balance_id,
      'dvd_date' => $form_state->getValue('date'),
      'dvd_is_income' => TRUE,
      'dvd_transaction_rate' => $exchange_rates,
      'dvd_amount_in_input_currency' => round($form_state->getValue('sum'), 2),
      'dvd_transaction_currency' => $form_state->getValue('sum_currency'),
      'dvd_old_balance' => $amount_department_balance,
      'dvd_transaction_description' => $description,
    ]);

    if ((in_array('ceo', $this->currentUser->getRoles()) && $form_state->getValue('from_another_person') === 1) || !in_array('ceo', $this->currentUser->getRoles())) {
      if ($form_state->getValue('options') === 'admin_department' && !empty($form_state->getValue('admin_departments'))) {
        $balance_transaction->set('dvd_admin_departments', $form_state->getValue('admin_departments'));
      }
      if ($form_state->getValue('options') === 'department' && !empty($form_state->getValue('departments'))) {
        $balance_transaction->set('dvd_departments', $form_state->getValue('departments'));
      }
      if ($form_state->getValue('options') === 'staff' && !empty($form_state->getValue('staff'))) {
        $balance_transaction->set('dvd_staff', $form_state->getValue('staff'));
      }
    }
    $log_message = $this->t('Income transaction - @transaction_name was added to Department Balance - @balance_name', [
      '@transaction_name' => 'Department transaction for ' . $department_balance->get('db_balance_name')->value,
      '@balance_name' => $department_balance->get('db_balance_name')->name,
    ]);
    $this->actionLog->log('add_transaction_to_department_balance', $log_message, $this->currentUser->id());
    $balance_transaction->save();
    $department_balance->set('db_amount', round($new_amount_department_balance, 2));
    $department_balance->save();

    $this->messenger()
      ->addStatus($this->t('Income transaction was successfully added'));
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
