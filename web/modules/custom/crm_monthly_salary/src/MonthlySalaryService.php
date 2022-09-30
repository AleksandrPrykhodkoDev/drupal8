<?php

namespace Drupal\crm_monthly_salary;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\crm_action_log\ActionLogService;
use Drupal\da_notification\SendMailService;
use Drupal\eck\Entity\EckEntity;
use Drupal\staff_module\Enum\Vacation;
use Drupal\staff_module\VacationsService;

/**
 * The MonthlySalaryHelper service.
 *
 * @package Drupal\crm_monthly_salary
 */
class MonthlySalaryService {

  use StringTranslationTrait;

  const FULLTIME = 'fulltime';
  const USD = 'dollars';
  const ADD_EVENT = 'add';
  const DELETE_EVENT = 'delete';
  const SALARY_INCOME_TYPE = 'st_salary';

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   *   The user storage.
   */
  protected $userStorage;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   *   The node storage.
   */
  protected $nodeStorage;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   *   The term storage.
   */
  protected $termStorage;

  /**
   * The eck salary agreement storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   *   The agreement storage.
   */
  protected $agreementStorage;

  /**
   * The eck weekend storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   *   The weekend storage.
   */
  protected $weekendStorage;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * SendMailService.
   *
   * @var \Drupal\da_notification\SendMailService
   */
  protected $sendMail;

  /**
   * The Vacation Service.
   *
   * @var \Drupal\staff_module\VacationsService
   */
  protected $vacationsService;

  /**
   * Action Log Service.
   *
   * @var \Drupal\crm_action_log\ActionLogService
   */
  protected $actionLog;

  /**
   * MonthlySalaryService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager service.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   Drupal\Core\Session\AccountProxy definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter.
   * @param \Drupal\da_notification\SendMailService $send_mail
   *   Send mail service.
   * @param \Drupal\staff_module\VacationsService $vacation_service
   *   The vacations service.
   * @param \Drupal\crm_action_log\ActionLogService $action_log
   *   Action log service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxy $current_user,
    DateFormatterInterface $date_formatter,
    SendMailService $send_mail,
    VacationsService $vacation_service,
    ActionLogService $action_log) {
    $this->currentUser = $current_user;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->agreementStorage = $entity_type_manager->getStorage('sal_agr_entity_type');
    $this->weekendStorage = $entity_type_manager->getStorage('weekend');
    $this->dateFormatter = $date_formatter;
    $this->sendMail = $send_mail;
    $this->vacationsService = $vacation_service;
    $this->actionLog = $action_log;
  }

  /**
   * Create Payment Statement entity for employee.
   *
   * @param int $employee_id
   *   Current employee id.
   * @param array $salary_data
   *   Array contains employee's salary and message for payment statement comment.
   */
  public function createPaymentStatement(int $employee_id, array $salary_data) : void {
    $salary_income_type = taxonomy_term_machine_name_load(self::SALARY_INCOME_TYPE, 'salary_type');
    $employee = $this->userStorage->load($employee_id);
    $employee_fio = $employee->get('name')->value;
    if (!empty($employee->get('u_applicants_fio'))) {
      $employee_fio = $employee->get('u_applicants_fio')->value;
    }
    $balance = current($this->nodeStorage->loadByProperties([
      'type' => 'balance_employee',
      'starting_balance_user_id' => $employee_id,
    ]));

    $old_balance = 0;
    if ($balance) {
      $old_balance = round($balance->get('starting_balance')->value, 2);
    }

    $new_balance = (float) $old_balance + round((float) $salary_data['salary'], 2);
    $current_date = new DrupalDateTime('now');
    $current_date_statement_storage = $current_date->format('Y-m-d');

    if (!$balance) {
      $new_employee_balance = $this->nodeStorage->create([
        'type' => 'balance_employee',
        'title' => 'Balance employee - ' . $employee_fio,
        'starting_balance' => round($new_balance, 2),
        'starting_balance_user_id' => $employee_id,
        'is_active_balance' => TRUE,
      ]);
      $new_employee_balance->save();
    }

    $payment_statement = $this->nodeStorage->create([
      'type' => 'payment_statement',
      'uid' => 1,
      'title' => $this->t('Monthly salary for user @employee_fio', [
        '@employee_fio' => $employee_fio,
      ]),
      'ps_amount' => round($salary_data['salary'], 2),
      'ps_balance' => $balance ? $balance->id() : $new_employee_balance->id(),
      'ps_comment' => $salary_data['message'],
      'ps_date' => $current_date_statement_storage,
      'ps_employee' => $employee_id,
      'ps_is_payroll' => 1,
      'ps_new_balance' => round($new_balance, 2),
      'ps_old_balance' => $old_balance,
      'ps_is_payments_transaction' => 1,
      'ps_transaction_type' => $salary_income_type->id(),
      'ps_transaction_period' => $this->getPreviousPeriodId(),
    ]);
    $payment_statement->save();

    if ($balance) {
      $balance->set('starting_balance', round($new_balance, 2));
      $balance->save();
    }

  }

  /**
   * Check if we have Payment Statement for this employee.
   *
   * @param int $employee_id
   *   Current employee id.
   * @param float|null $salary
   *   Salary data for calculations.
   * @param int|null $period_id
   *   Period id for query.
   *
   * @return bool
   *   Is employee has payment statement entry for this period.
   */
  public function checkIfWeHavePaymentStatementForThisEmployee(int $employee_id, float $salary = NULL, int $period_id = NULL): bool {
    // Here we need to build query to the database and check if we already have an entry.
    $current_entry_for_user = TRUE;
    $salary_income_type = taxonomy_term_machine_name_load(self::SALARY_INCOME_TYPE, 'salary_type');
    $query = $this->nodeStorage->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('type', 'payment_statement');
    $query->condition('ps_employee', $employee_id);
    $query->condition('ps_is_payroll', 1);
    $query->condition('ps_transaction_type', $salary_income_type->id());
    if (isset($salary)) {
      $query->condition('ps_is_payments_transaction', FALSE);
    }
    $query->condition('ps_transaction_period', !empty($period_id) ? $period_id : $this->getPreviousPeriodId());
    $payment_statement_id = $query->execute();

    $payment_statement_salary_id = [];
    if (isset($salary)) {
      $query = $this->nodeStorage->getQuery();
      $query->accessCheck(FALSE);
      $query->condition('type', 'payment_statement');
      $query->condition('ps_employee', $employee_id);
      $query->condition('ps_is_payroll', 1);
      $query->condition('ps_transaction_type', $salary_income_type->id());
      $query->condition('ps_amount', $salary);
      $query->condition('ps_transaction_period', $this->getPreviousPeriodId());
      $payment_statement_salary_id = $query->execute();
    }

    if (empty($payment_statement_id) && empty($payment_statement_salary_id)) {
      $current_entry_for_user = FALSE;
    }

    return $current_entry_for_user;
  }

  /**
   * Get all unfired users.
   *
   * @return array
   *   Array with ids of unfired users.
   */
  public function getUsersToCalculateSalary() : array {
    $staff_query = $this->userStorage->getQuery()->accessCheck(FALSE);
    $staff_query->condition('status', TRUE);
    $staff_query->condition('u_is_a_staff', TRUE);
    $staff_ids = $staff_query->execute();

    foreach ($staff_ids as $employee_id) {
      $employee_balance_id = $this->nodeStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'balance_employee')
        ->condition('starting_balance_user_id', $employee_id)
        ->condition('is_active_balance', FALSE)
        ->execute();

      // Here we check that employee is fired.
      $employee = $this->userStorage->load($employee_id);
      if (!empty($employee->get('u_fired')) && $employee->get('u_fired')->value == 1) {
        $prev_period_id = $this->getPreviousPeriodId();
        $is_fired_in_prev_period = $this->isEmployeeFiredInPrevPeriod($employee_id, $prev_period_id);
        // Check that employee was fired not in the previous month.
        if (!$is_fired_in_prev_period) {
          unset($staff_ids[$employee_id]);
        }
      }
      if (!empty($employee_balance_id)) {
        unset($staff_ids[$employee_id]);
      }
    }

    return $staff_ids;
  }

  /**
   * Get previous period id.
   *
   * @return int
   *   Previous period id.
   */
  public function getPreviousPeriodId(): int {
    // Here we need to get Current period and return its ID.
    $current_date = new DrupalDateTime('now', 'UTC');
    $prev_month = $current_date->modify('-1 month')->format('Y-m-d');

    $query = $this->termStorage->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('vid', 'billable_range');
    $query->condition('br_invoice_period.value', $prev_month, '<=');
    $query->condition('br_invoice_period.end_value', $prev_month, '>=');
    $tids = $query->execute();

    $current_id = array_shift($tids);

    return $current_id;
  }

  /**
   * Get salary data.
   *
   * @param array $agreement_data
   *   Array with current employee data.
   * @param int $employee_id
   *   Employee id.
   *
   * @return array
   *   Array with salary data.
   */
  public function getSalaryData(array $agreement_data, int $employee_id) : array {
    $salary_data = ['salary' => 0];
    $additional_data = [];

    $count_days_in_range = $agreement_data['end_date']->diff($agreement_data['start_date'])->days + 1;
    $count_business_days_in_range = $this->vacationsService->getBusinessDaysInDaysRange($agreement_data['start_date'], $count_days_in_range, $employee_id);
    $final_days = $count_business_days_in_range - $agreement_data['unpaid_days'];
    $count_business_days_in_month = $this->getBusinessDaysInMonth($employee_id);
    if ($count_business_days_in_month) {
      $additional_data['agreement_rate'] = $agreement_data['rate'];
      $additional_data['department_id'] = $agreement_data['department_id'];
      $additional_data['currency'] = $agreement_data['currency'];
      $additional_data['count_b_days'] = $final_days;
      $additional_data['unpaid_leaves'] = $agreement_data['unpaid_days'];
      $additional_data['agreements_start_date'] = $agreement_data['start_date']->format('d-m-Y');
      $additional_data['agreements_end_date'] = $agreement_data['end_date']->format('d-m-Y');
      $additional_data['rate'] = $agreement_data['rate'];
      $additional_data['count_business_days_in_month'] = $count_business_days_in_month;
      if (is_numeric($agreement_data['rate'])) {
        $salary_data['salary'] = $agreement_data['rate'] * $final_days / $count_business_days_in_month;
      }
    }
    $salary_data['message'] = $this->prepareMessageForTransactions($salary_data['salary'], $additional_data, $employee_id);

    return $salary_data;
  }

  /**
   * Prepare message for transactions.
   *
   * @param string $salary
   *   Salary value from agreement.
   * @param array $additional_data
   *   Array with some additional data.
   * @param int $employee_id
   *   Employee id.
   *
   * @return string
   *   Message for payment statement comment.
   */
  public function prepareMessageForTransactions(string $salary, array $additional_data, int $employee_id) : string {
    $current_period = new DrupalDateTime('now', 'UTC');
    $prev_period = clone ($current_period)->modify('- 1 month');
    $month = $prev_period->format('F');
    $usd_machine_name = 'dollars';
    $currency_name = '';
    $rate = NULL;
    $count_vacation_days = $this->countVacationDaysInPeriod($this->getPreviousPeriodId(), $employee_id, Vacation::VACATION_TYPE_VACATION);
    $count_sick_days = $this->countSickDaysInPeriod($this->getPreviousPeriodId(), $employee_id);
    $agreements_start_date = $additional_data['agreements_start_date'];
    $agreements_end_date = $additional_data['agreements_end_date'];
    $formula = $this->t('@rate$ * @final_days(working days in agreements range excluding Unpaid leave) / @count_business_days_in_month(working days in @month) = @salary$', [
      '@rate' => $additional_data['agreement_rate'],
      '@final_days' => $additional_data['count_b_days'],
      '@count_business_days_in_month' => $additional_data['count_business_days_in_month'],
      '@salary' => round($salary, 2),
      '@month' => $month,
    ]);
    if (!empty($additional_data['currency'])) {
      $currency = taxonomy_term_machine_name_load($additional_data['currency'], 'currency');
      $currency_name = $currency->getName();
      if ($additional_data['currency'] !== $usd_machine_name) {
        $rate = $this->getRateByCurrencyAndDepartment($additional_data['currency'], $additional_data['department_id']);
        $formula = $this->t('@rate@currency / @exchange_rate(exchange rate) * @final_days(working days in agreements range excluding Unpaid leave) / @count_business_days_in_month(working days in @month) = @salary$', [
          '@rate' => $additional_data['agreement_rate'],
          '@exchange_rate' => $rate,
          '@final_days' => $additional_data['count_b_days'],
          '@count_business_days_in_month' => $additional_data['count_business_days_in_month'],
          '@salary' => !empty($rate) ? round($salary / $rate, 2) : round($salary, 2),
          '@currency' => $currency_name,
          '@month' => $month,
        ]);
      }
    }

    return $this->t('<div><p><strong>Create by - System transaction</strong></p>
                                    <p>Salary calculation for @month: @salary</p>
                                    <p>Agreements from @start_date to @end_date</p>
                                    <p>Calculation: @formula</p>
                                    <p>Rate: @agreement_rate</p>
                                    <p>Working days: @count_b_days</p>
                                    <p>Unpaid leaves: @count_unpaid_leaves</p>
                                    <p>General vacation days for @month: @vacation_days</p>
                                    <p>General sick days for @month: @sick_days</p></div>', [
                                      '@month' => $month,
                                      '@agreement_rate' => $additional_data['agreement_rate'] . $currency_name,
                                      '@salary' => !empty($rate) ? round($salary / $rate, 2) . '$' : round($salary, 2) . '$',
                                      '@count_b_days' => $additional_data['count_b_days'],
                                      '@count_unpaid_leaves' => $additional_data['unpaid_leaves'],
                                      '@vacation_days' => $count_vacation_days,
                                      '@sick_days' => $count_sick_days,
                                      '@start_date' => $agreements_start_date,
                                      '@end_date' => $agreements_end_date,
                                      '@formula' => $formula,
                                    ]);
  }

  /**
   * Get agreements from billable period.
   *
   * @param int $employee_id
   *   Employee id.
   * @param int $prev_period_id
   *   Prev period id.
   */
  public function getAgreementsFromBillablePeriod(int $employee_id, int $prev_period_id): array {
    $employee = $this->userStorage->load($employee_id);
    $employee_department = $this->getAdminDepartmentByEmployee($employee_id);
    if (!empty($employee_department)) {
      $department = $this->termStorage->load($employee_department);
    }
    $is_administrative = FALSE;
    if (!empty($department) && !empty($department->get('is_administrative'))) {
      if ($department->get('is_administrative')->value == 1) {
        $is_administrative = TRUE;
      }
    }
    $current_period = $this->termStorage->load($prev_period_id);

    $current_month_start_date_obj = new DrupalDateTime($current_period->get('br_invoice_period')->value, 'UTC');
    $current_month_end_date_obj = new DrupalDateTime($current_period->get('br_invoice_period')->end_value, 'UTC');

    $fulltime_sal_agr_assessment_type = $this->termStorage->loadByProperties([
      'vid' => 'assessments_type',
      'machine_name' => self::FULLTIME,
    ]);

    $result_is_hired = $this->isEmployeeHiredInCurrentPeriod($employee_id, $prev_period_id);
    if ($result_is_hired) {
      $employee_hired_date = new DrupalDateTime($employee->get('u_hire_date')->value, 'UTC');
      $start_date = $employee_hired_date;
    }
    else {
      $start_date = clone $current_month_start_date_obj;
    }

    $result_is_fired = $this->isEmployeeFiredInPrevPeriod($employee_id, $prev_period_id);
    if ($result_is_fired) {
      $employee_fired_date = new DrupalDateTime($employee->get('u_fire_date')->value, 'UTC');
      $end_date = $employee_fired_date;
    }
    else {
      $end_date = clone $current_month_end_date_obj;
    }

    // Formatting dates to exclude time from comparison.
    $agreements_query = $this->agreementStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('sal_agr_agreements_subject', $employee_id)
      ->condition('sal_agr_agreements_start_date', $end_date->format('Y-m-d'), '<=')
      ->condition('sal_agr_assessment_type', current($fulltime_sal_agr_assessment_type)->id());
    $or_group = $agreements_query->orConditionGroup()
      ->condition('sal_agr_agreements_end_date', $start_date->format('Y-m-d'), '>=')
      ->condition('sal_agr_agreements_end_date', NULL, 'IS NULL');
    $agreements_ids = $agreements_query->condition($or_group)
      ->sort('sal_agr_agreements_start_date', 'DESC')
      ->execute();

    $agreements_prepared_data = [];
    $agreements = $this->agreementStorage->loadMultiple($agreements_ids);

    // An id of an agreement, which will start after a current one.
    $next_agreement_key = '';
    foreach ($agreements as $key => $agreement) {

      // Assigning working days period of this month (start/end dates).
      $agreements_prepared_data[$key]['start_date'] = $start_date;
      $agreements_prepared_data[$key]['end_date'] = $end_date;

      // Agreement's end date.
      if (!empty($agreement->get('sal_agr_agreements_end_date')->value)) {
        $agreement_end_date = new DrupalDateTime($agreement->get('sal_agr_agreements_end_date')->value, 'UTC');
        if ($agreement_end_date >= $start_date && $agreement_end_date <= $end_date) {
          $agreements_prepared_data[$key]['end_date'] = $agreement_end_date;
        }
      }
      elseif (!empty($agreements_prepared_data[$next_agreement_key]['end_date'])) {
        $day_before_next_agreement = (clone $agreements_prepared_data[$next_agreement_key]['start_date'])->modify('-1 day');
        if ($day_before_next_agreement < $start_date) {
          unset($agreements_prepared_data[$key]);
          continue;
        }
        $agreements_prepared_data[$key]['end_date'] = $day_before_next_agreement;
      }

      // Agreement's start date.
      if (!empty($agreement->get('sal_agr_agreements_start_date')->value)) {
        $agreement_start_date = new DrupalDateTime($agreement->get('sal_agr_agreements_start_date')->value, 'UTC');
        if ($agreement_start_date < $start_date) {
          $agreements_prepared_data[$key]['start_date'] = $start_date;
        }
        else {
          $agreements_prepared_data[$key]['start_date'] = $agreement_start_date;
        }
      }

      // Here we get additional data from agreement.
      if (!empty($agreement->get('sal_agr_assessment_type'))) {
        $agreements_prepared_data[$key]['employee_availability'] = $agreement->get('sal_agr_assessment_type')->referencedEntities()[0]->get('name')->value;
      }
      if (!empty($agreement->get('sl_agr_rate_fulltime'))) {
        $agreements_prepared_data[$key]['rate'] = $agreement->get('sl_agr_rate_fulltime')->value;
      }
      if (!empty($agreement->get('sal_agr_rate_currency'))) {
        $agreements_prepared_data[$key]['currency'] = $agreement->get('sal_agr_rate_currency')->referencedEntities()[0]->get('machine_name')->value;
      }
      $agreements_prepared_data[$key]['department_id'] = $is_administrative ? $employee_department : '';

      // Update next_agreement_key for a next iteration.
      $next_agreement_key = $key;
    }

    foreach ($agreements_prepared_data as $key => $agreement_prepared_data) {
      $unpaid_leaves_ids = $this->getUnpaidLeavesIds($employee_id, $agreement_prepared_data['start_date'], $agreement_prepared_data['end_date']);
      $count_unpaid_leave_days = $this->helpCountUnpaidLeavesDays($unpaid_leaves_ids, $agreement_prepared_data);
      $agreements_prepared_data[$key]['unpaid_days'] = $count_unpaid_leave_days;
    }

    return $agreements_prepared_data;
  }

  /**
   * Get business days in month.
   *
   * @param int $employee_id
   *   Employee id.
   * @param int|null $period_id
   *   Billable period id.
   *
   * @return int
   *   Count business days in month.
   */
  public function getBusinessDaysInMonth(int $employee_id, int $period_id = NULL) : int {
    $billable_range_id = !empty($period_id) ? $period_id : $this->getPreviousPeriodId();
    $current_period = $this->termStorage->load($billable_range_id);
    $start_date = new DrupalDateTime($current_period->get('br_invoice_period')->value);
    $end_date = new DrupalDateTime($current_period->get('br_invoice_period')->end_value);
    $sum_days = $end_date->diff($start_date)->days + 1;
    $business_days = $this->vacationsService->getPreparedDataToCountBusinessDays(clone $start_date, $sum_days);
    $employee = $this->userStorage->load($employee_id);
    $department = $this->vacationsService->getDepartmentLinkedCalendar($employee);
    $calendars_data = $this->vacationsService->getHolidaysAndTransfers($department, $start_date, $end_date);
    $count_b_days = 0;

    foreach ($business_days as $business_day) {
      if (in_array($business_day, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'])) {
        $count_b_days++;
      }
    }

    return $count_b_days + count($calendars_data['transfers']) - count($calendars_data['holidays']);
  }

  /**
   * Get business days in month (by calendar).
   *
   * @param int|string $calendar_id
   *   The calendar id.
   * @param int|string $period_id
   *   Billable period id.
   *
   * @return int
   *   Count business days in month.
   */
  public function getBusinessDaysInMonthByCalendar($calendar_id, $period_id) {
    $current_period = $this->termStorage->load($period_id);
    $start_date = new DrupalDateTime($current_period->get('br_invoice_period')->value);
    $end_date = new DrupalDateTime($current_period->get('br_invoice_period')->end_value);

    $working_days = $this->vacationsService->getWorkingDaysInRange($start_date, $end_date);
    $holidays_and_transfers = $this->vacationsService->getHolidaysAndTransfersByCalendar($calendar_id, $start_date, $end_date);

    return $working_days + count($holidays_and_transfers['transfers']) - count($holidays_and_transfers['holidays']);
  }

  /**
   * Get department calendar id.
   *
   * @param string $department_id
   *   Administrative department id.
   * @param int|string $year
   *   A year.
   *
   * @return array|null
   *   Array with department calendars ids or null.
   */
  public function getDepartmentCalendarId(string $department_id, $year = ''): ?array {
    if (empty($year)) {
      $current_date = new DrupalDateTime('now', 'UTC');
      $year = $current_date->format('Y');
    }

    return $this->nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'calendar')
      ->condition('cl_admin_department', (int) $department_id)
      ->condition('cl_year', (int) $year)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
  }

  /**
   * Send notifications about empty department calendar.
   *
   * @param array $department_ids
   *   Department with empty department calendars.
   */
  public function sendNotificationsAboutEmptyDepartmentCalendar(array $department_ids) : void {
    $departments = [];

    foreach ($department_ids as $department_id) {
      if (array_key_exists($department_id, $departments)) {
        continue;
      }
      $department = $this->termStorage->load($department_id);
      if (!empty($department) && !empty($department->get('department_head'))) {
        $uids_receive_notifications = $department->get('department_head')->target_id;
        $message = $this->t('To calculate salary, you must fill in the work calendar for the administrative department - @department_name or add the administrative calendar to the created work calendar', [
          '@department_name' => $department->get('name')->value,
        ]);
        $mail_options = [
          'uids_receive_notifications' => [$uids_receive_notifications],
        ];
        if (!empty($message)) {
          $this->sendMail->sendEmails($mail_options, $message, 'salary_error');
        }
        $departments[$department_id] = $department_id;
      }
    }
  }

  /**
   * Get business days in days range.
   *
   * @param int $employee_id
   *   Current employee id.
   * @param object $start_date
   *   DateTime object of start range date.
   * @param object $end_date
   *   DateTime object of end range date.
   *
   * @return array
   *   Array with Unpaid Leaves ids.
   */
  public function getUnpaidLeavesIds(int $employee_id, object $start_date, object $end_date): array {
    $unpaid_leave_taxonomy_term_id = taxonomy_term_machine_name_load('unpaid_leave', 'vacation_types')->id();

    // Formatting dates to exclude time from comparison.
    $start_date = $start_date->format('Y-m-d');
    $end_date = $end_date->format('Y-m-d');

    $query = $this->nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'vacation')
      ->condition('vacation_type', $unpaid_leave_taxonomy_term_id)
      ->condition('vacation_employee', $employee_id);

    $date_condition_group_1 = $query->andConditionGroup();
    $date_condition_group_1->condition('vacation_date_from', [
      $start_date,
      $end_date,
    ], 'BETWEEN');
    $date_condition_group_1->condition('vacation_date_to', [
      $start_date,
      $end_date,
    ], 'BETWEEN');

    $date_condition_group_2 = $query->andConditionGroup();
    $date_condition_group_2->condition('vacation_date_from', $start_date, '<=');
    $date_condition_group_2->condition('vacation_date_to', [
      $start_date,
      $end_date,
    ], 'BETWEEN');

    $date_condition_group_3 = $query->andConditionGroup();
    $date_condition_group_3->condition('vacation_date_from', [
      $start_date,
      $end_date,
    ], 'BETWEEN');
    $date_condition_group_3->condition('vacation_date_to', $end_date, '>=');

    $date_condition_group = $query->orConditionGroup()
      ->condition($date_condition_group_1)
      ->condition($date_condition_group_2)
      ->condition($date_condition_group_3);

    $unpaid_leaves_ids = $query
      ->condition($date_condition_group)
      ->execute();

    return $unpaid_leaves_ids;
  }

  /**
   * Helper function to count Unpaid Leaves days.
   *
   * @param array $unpaid_leaves_ids
   *   Array with unpaid leaves ids.
   * @param array $agreement_prepared_data
   *   Employees agreements data.
   *
   * @return int
   *   Count business days in unpaid leaves range.
   */
  public function helpCountUnpaidLeavesDays(array $unpaid_leaves_ids, array $agreement_prepared_data): int {
    $unpaid_leaves = $this->nodeStorage->loadMultiple($unpaid_leaves_ids);

    $count_b_days = 0;
    foreach ($unpaid_leaves as $unpaid_leave) {
      $start_date_obj = new DrupalDateTime($unpaid_leave->get('vacation_date_from')->value, 'UTC');
      $end_date_obj = new DrupalDateTime($unpaid_leave->get('vacation_date_to')->value, 'UTC');
      if ($agreement_prepared_data['start_date'] > $start_date_obj) {
        $start_date_obj = $agreement_prepared_data['start_date'];
      }
      if ($agreement_prepared_data['end_date'] < $end_date_obj) {
        $end_date_obj = $agreement_prepared_data['end_date'];
      }

      $initial_date = clone $start_date_obj;
      $count_days = $end_date_obj->diff($start_date_obj)->days + 1;
      $business_days = $this->vacationsService->getPreparedDataToCountBusinessDays($initial_date, $count_days);

      foreach ($business_days as $business_day) {
        if (in_array($business_day, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'])) {
          $count_b_days++;
        }
      }
    }

    return $count_b_days;
  }

  /**
   * Check is employee hired in current period.
   *
   * @param int $employee_id
   *   Current User ID.
   * @param int $current_period_id
   *   Current salary period ID.
   *
   * @return bool
   *   Return result is employee hired in current period.
   */
  public function isEmployeeHiredInCurrentPeriod(int $employee_id, int $current_period_id) : bool {
    $current_period = $this->termStorage->load($current_period_id);

    $hired_employee_id = $this->userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', TRUE)
      ->condition('u_hire_date', $current_period->get('br_invoice_period')->value, '>=')
      ->condition('u_hire_date', $current_period->get('br_invoice_period')->end_value, '<=')
      ->condition('uid', $employee_id)
      ->execute();

    return count($hired_employee_id) > 0;
  }

  /**
   * Get admin department by employee id.
   *
   * @param int $employee_id
   *   Employee id.
   *
   * @return int|null
   *   Id of administrative department.
   */
  public function getAdminDepartmentByEmployee(int $employee_id) : ?int {
    $employee = $this->userStorage->load($employee_id);
    if (!empty($employee->get('u_executive_department'))) {
      $department_id = $employee->get('u_executive_department')->target_id;
    }

    return $department_id ?? NULL;
  }

  /**
   * Get currencies.
   *
   * @return array
   *   Array with currencies.
   */
  public function getCurrencies() : array {
    $currency_options = [];
    $currencies = $this->termStorage->loadByProperties([
      'vid' => 'currency',
    ]);
    foreach ($currencies as $currency) {
      $currency_options[$currency->id()] = $currency->get('name')->value;
    }

    return $currency_options;
  }

  /**
   * Get current month tid from billable_pay_period taxonomy vocabulary.
   *
   * @return string|int
   *   Taxonomy term billable_pay_period tid.
   */
  public function getCurrentPeriod() {
    $current_date = new DrupalDateTime('now');
    $current_date_string = $current_date->format('Y-m-d');

    $query = $this->termStorage->getQuery()
      ->accessCheck(FALSE);
    $query->condition('vid', 'billable_range');
    $query->condition('br_invoice_period.value', $current_date_string, '<=');
    $query->condition('br_invoice_period.end_value', $current_date_string, '>=');
    $tids = $query->execute();

    $current = array_shift($tids);

    return $current;
  }

  /**
   * Get all periods ids.
   *
   * @return array
   *   Array contains of pay periods ids.
   */
  public function getAllPeriodsIds() : array {
    $tids = $this->termStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'billable_range')
      ->condition('status', 1)
      ->sort('br_invoice_period.end_value', 'DESC')
      ->execute();

    return $tids;
  }

  /**
   * Get periods of the year.
   *
   * @param int|string $year
   *   A year of periods.
   *
   * @return array
   *   Array of periods entities.
   */
  public function getPeriodsByYear($year) {
    $tids = $this->termStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'billable_range')
      ->condition('status', 1)
      ->condition('br_invoice_period.value', $year . '-01-01', '>=')
      ->condition('br_invoice_period.value', $year . '-12-01', '<=')
      ->sort('br_invoice_period.end_value')
      ->execute();

    return $this->termStorage->loadMultiple($tids);
  }

  /**
   * Helper function to get previous month period.
   *
   * @param string $billable_period_tid
   *   Current period tid.
   *   Available periods array.
   *
   * @return string|null
   *   Return previous period or NULL.
   */
  public function getPreviousPeriod(string $billable_period_tid): ?string {
    $current_period = $this->termStorage->load($billable_period_tid);
    $prev_period_id = current($this->termStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'billable_range')
      ->condition('br_invoice_period.end_value', $current_period->get('br_invoice_period')->value, '<')
      ->sort('br_invoice_period', 'DESC')
      ->execute());

    return $prev_period_id ?? NULL;
  }

  /**
   * Helper function to get next month period.
   *
   * @param string $billable_period_tid
   *   Current period tid.
   *   Available periods array.
   *
   * @return string|null
   *   Return next period or NULL.
   */
  public function getNextPeriod(string $billable_period_tid): ?string {
    $current_period = $this->termStorage->load($billable_period_tid);
    $next_period_id = current($this->termStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'billable_range')
      ->condition('br_invoice_period.value', $current_period->get('br_invoice_period')->end_value, '>')
      ->sort('br_invoice_period', 'ASC')
      ->execute());

    return $next_period_id ?? NULL;
  }

  /**
   * Create link with modified dates.
   *
   * @param string|int $date
   *   Id of the billable period.
   * @param string|int $link_text
   *   Additional param to method(>> || <<).
   *
   * @return array
   *   Array of link.
   */
  public function createLinkWithModifiedDates($date, $link_text) {
    $url = Url::fromRoute('crm_monthly_salary.show_exchanges_rate_page');
    $parameters['period'] = $date;
    $url->setOptions(['query' => $parameters]);

    $link = Link::fromTextAndUrl($link_text, $url);
    $link = $link->toRenderable();

    return $link;
  }

  /**
   * Get links for period pay period links.
   *
   * @param string|int $default_billable_period_tid
   *   Id of the default billable period.
   *
   * @return array
   *   Array of links.
   */
  public function getPeriodLinks($default_billable_period_tid) {
    $links = [];
    $links['prev_month_link'] = '';
    if ($this->getPreviousPeriod($default_billable_period_tid)) {
      $links['prev_month_link'] = $this->createLinkWithModifiedDates($this->getPreviousPeriod(
        $default_billable_period_tid
      ), '<<');
    }
    $links['next_month_link'] = '';
    if ($this->getNextPeriod($default_billable_period_tid)) {
      $links['next_month_link'] = $this->createLinkWithModifiedDates($this->getNextPeriod(
        $default_billable_period_tid
      ), '>>');
    }

    $billable_period = $this->termStorage->load($default_billable_period_tid);
    $date_value = new DrupalDateTime($billable_period->get('br_invoice_period')->value);
    $month = $date_value->format('F');
    $links['current_period'] = $this->t('Salary exchange rate for <strong>@month</strong>', [
      '@month' => $month,
    ]);

    return $links;
  }

  /**
   * Get rate by currency and department.
   *
   * @param string $currency_machine_name
   *   Employee's currency machine name.
   * @param int $department_id
   *   Employee's department id.
   *
   * @return string|null
   *   Salary exchange rate or null.
   */
  public function getRateByCurrencyAndDepartment(string $currency_machine_name, int $department_id): ?string {
    $result = NULL;
    $current_currency = taxonomy_term_machine_name_load($currency_machine_name, 'currency');
    $current_period = $this->getCurrentPeriod();
    $current_exchange_rate = current($this->nodeStorage->loadByProperties([
      'type' => 'salary_exchange_rate',
      'ser_period' => $current_period,
      'ser_currency' => $current_currency->id(),
      'ser_administrative_department' => $department_id,
    ]));
    if (!empty($current_exchange_rate)) {
      return $current_exchange_rate->get('ser_rate')->value;
    }

    return $result;
  }

  /**
   * Get message for notification.
   *
   * @param array $currencies
   *   Currencies that need to be fill in.
   * @param string $department_name
   *   Administrative department title.
   *
   * @return object
   *   Translatable object.
   */
  public function getMessageForNotification(array $currencies, string $department_name) : object {
    $url = Url::fromRoute('crm_monthly_salary.show_exchanges_rate_page');
    $link_to_rates = Link::fromTextAndUrl($this->t('Exchange rates'), $url);
    $link_to_rates = $link_to_rates->toString();
    $message = $this->t('To calculate the salaries of employees, please fill in the exchange rates of <strong>@currencies</strong> for the department <strong>@department</strong> on the @link page.', [
      '@currencies' => implode(', ', $currencies),
      '@department' => $department_name,
      '@link' => $link_to_rates,
    ]);

    return $message;
  }

  /**
   * Is notifications for Ceo.
   *
   * @return bool
   *   Is for ceo.
   */
  public function isNotificationsForCeo() : bool {
    $current_date = new DrupalDateTime('01-04-2022', 'UTC');
    $current_day = $current_date->format('d');

    return $current_day > 8;
  }

  /**
   * Calculate salary for non usd staff.
   *
   * @param array $staff
   *   Staff with non dollar currency.
   */
  public function calculateSalaryForNonUsdStaff(array $staff) : void {
    $no_rate_by_department = [];
    if (count($staff) > 0) {
      foreach ($staff as $employee_data) {
        $department_id = $employee_data['department_id'];
        if (!empty($department_id)) {
          $rate = $this->getRateByCurrencyAndDepartment($employee_data['currency'], $department_id);
          if ($rate) {
            $employee_data['salary_data']['salary'] = round($employee_data['salary_data']['salary'] / $rate, 2);

            if (empty($this->checkIfWeHavePaymentStatementForThisEmployee($employee_data['employee_id'], round($employee_data['salary_data']['salary'], 2)))) {
              $this->createPaymentStatement($employee_data['employee_id'], $employee_data['salary_data']);
            }
          }
          else {
            $no_rate_by_department[$department_id][$employee_data['currency']] = $employee_data['currency'];
          }
        }
      }
    }
    if (count($no_rate_by_department) > 0) {
      $this->sendNotificationAboutFillInCurrencyRate($no_rate_by_department);
    }

  }

  /**
   * Send notification about fill in currency rate.
   *
   * @param array $departments_ids
   *   Departments ids without fill in exchange rates.
   */
  public function sendNotificationAboutFillInCurrencyRate(array $departments_ids) : void {
    $ceo_id = current($this->userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', 'ceo')
      ->execute());
    $department_name = '';
    if (count($departments_ids) > 0) {
      foreach ($departments_ids as $department_id => $currencies) {
        $department = $this->termStorage->load($department_id);
        if (!empty($department->get('name'))) {
          $department_name = $department->getName();
        }
        $message = $this->getMessageForNotification($currencies, $department_name);
        $mail_options = [
          'uids_receive_notifications' => $this->isNotificationsForCeo() ? [$ceo_id] : [$department->get('department_head')->target_id],
        ];
        if (!empty($message)) {
          $this->sendMail->sendEmails($mail_options, $message, 'exchange_rate');
        }
      }
    }
  }

  /**
   * Get period id by exchange rate date.
   *
   * @param string $date
   *   The date from form state.
   *
   * @return int
   *   Id of the period.
   */
  public function getPeriodIdByExchangeRateDate(string $date) : int {
    $query = $this->termStorage->getQuery()
      ->accessCheck(FALSE);
    $query->condition('vid', 'billable_range');
    $query->condition('br_invoice_period.value', $date, '<=');
    $query->condition('br_invoice_period.end_value', $date, '>=');

    return current($query->execute());
  }

  /**
   * Check is employee fired in current period.
   *
   * @param int $employee_id
   *   Current User ID.
   * @param int $prev_period_id
   *   Previous salary period ID.
   *
   * @return bool
   *   Return result is employee fired in current period.
   */
  public function isEmployeeFiredInPrevPeriod(int $employee_id, int $prev_period_id) : bool {
    $prev_period = $this->termStorage->load($prev_period_id);

    $fired_employee_id = $this->userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', TRUE)
      ->condition('u_fired', TRUE)
      ->condition('u_fire_date', $prev_period->get('br_invoice_period')->value, '>=')
      ->condition('u_fire_date', $prev_period->get('br_invoice_period')->end_value, '<=')
      ->condition('uid', $employee_id)
      ->execute();

    return count($fired_employee_id) > 0;
  }

  /**
   * Recalculate salary with unpaid leave.
   *
   * @param string $date_from
   *   Unpaid leave start date in string format.
   * @param string $date_to
   *   Unpaid leave end date in string format.
   * @param int $employee_id
   *   Id of the employee.
   * @param string $event
   *   Event for recalculating salary.
   */
  public function prepareRecalculatingSalaryByUnpaidLeave(string $date_from, string $date_to, int $employee_id, string $event) : void {
    $start_date = new DrupalDateTime($date_from);
    $end_date = new DrupalDateTime($date_to);
    $count_days = $end_date->diff($start_date)->days + 1;
    $days = $this->getDaysFromRangeWithoutWeekends(clone $start_date, $count_days, clone $end_date, $employee_id);
    $rates = [];
    $counter = 1;
    $prev_period_id = NULL;
    $period_id = NULL;
    foreach ($days as $day) {
      if ($this->checkIfWeHavePaymentStatementForThisEmployee($employee_id, NULL, $this->getPeriodIdByExchangeRateDate($day))) {
        $period_id = $this->getPeriodIdByExchangeRateDate($day);
        if ($prev_period_id === $period_id) {
          $rates[$this->getRateByDate($employee_id, $day)]['count_unpaid_days'] = $counter;
          $counter++;
        }
        $prev_period_id = $period_id;
        $rates[$this->getRateByDate($employee_id, $day)]['count_unpaid_days'] = $counter;
        $rates[$this->getRateByDate($employee_id, $day)]['rate'] = $this->getRateByDate($employee_id, $day);
      }
    }
    $count_b_days_in_month = $this->getBusinessDaysInMonth($employee_id, $period_id);
    $data_for_recalculation = [
      'event' => $event,
      'count_b_days_in_month' => $count_b_days_in_month,
      'employee_id' => $employee_id,
      'period_id' => $period_id,
    ];
    $this->recalculateSalary($rates, $data_for_recalculation);
  }

  /**
   * Recalculate Salary.
   *
   * @param array $rates
   *   Contains rates and count unpaid days in unpaid leaves period.
   * @param array $data
   *   Contains event, count_b_days_in_month, employee_id and period_id values.
   */
  public function recalculateSalary(array $rates, array $data) : void {
    $employee = $this->userStorage->load($data['employee_id']);
    $employee_fio = $employee->get('name')->value;
    if (!empty($employee) && !empty($employee->get('u_applicants_fio'))) {
      $employee_fio = $employee->get('u_applicants_fio')->value;
    }
    $salary_income_type = taxonomy_term_machine_name_load(self::SALARY_INCOME_TYPE, 'salary_type');

    $new_balance = NULL;

    $date = new DrupalDateTime('now', 'UTC');
    $date_format = $date->format('Y-m-d');
    $period = $this->termStorage->load($data['period_id']);
    $period_name = NULL;
    if (!empty($period) && !empty($period->get('name'))) {
      $period_name = $period->get('name')->value;
    }
    $old_count_unpaid_days = NULL;

    $is_add_event = $data['event'] == self::ADD_EVENT;
    $is_delete_event = $data['event'] == self::DELETE_EVENT;

    foreach ($rates as $rate) {
      $balance = current($this->nodeStorage->loadByProperties([
        'type' => 'balance_employee',
        'starting_balance_user_id' => $data['employee_id'],
      ]));

      // Here we check for another rate count unpaid leave days.
      if ($rate['count_unpaid_days'] !== $old_count_unpaid_days) {
        $rate['count_unpaid_days'] = $rate['count_unpaid_days'] - $old_count_unpaid_days;
      }
      if (!empty($data['count_b_days_in_month']) && $balance) {
        $result = round((int) $rate['count_unpaid_days'] / (int) $data['count_b_days_in_month'] * substr($rate['rate'], 0, -1), 2);
        $old_balance = round($balance->get('starting_balance')->value, 2);
        if ($is_add_event) {
          $new_balance = $old_balance - round($result, 2);
        }
        if ($is_delete_event) {
          $new_balance = $old_balance + round($result, 2);
        }

        $ps_comment = $this->t('<p>Clarification of the amount of Unpaid leave for @month: @result$</p>
                                      <p>@action Unpaid Leave: @count_unpaid_days</p>
                                      <p>Rate: @rate$</p>', [
                                        '@month' => $period_name,
                                        '@result' => $result,
                                        '@count_unpaid_days' => $rate['count_unpaid_days'],
                                        '@rate' => round($rate['rate'], 2),
                                        '@action' => $is_add_event ? 'Added' : 'Deleted',
                                      ]);

        $payment_statement = $this->nodeStorage->create([
          'type' => 'payment_statement',
          'uid' => 1,
          'title' => $this->t('Update Payment Statement for user @employee_fio', [
            '@employee_fio' => $employee_fio,
          ]),
          'ps_amount' => $result,
          'ps_balance' => $balance->id(),
          'ps_comment' => $ps_comment,
          'ps_date' => $date_format,
          'ps_employee' => $data['employee_id'],
          'ps_is_payroll' => $is_add_event ? 0 : 1,
          'ps_new_balance' => round($new_balance, 2),
          'ps_old_balance' => $old_balance,
          'ps_is_payments_transaction' => 1,
          'ps_transaction_period' => $data['period_id'],
        ]);
        if ($is_delete_event) {
          $payment_statement->set('ps_transaction_type', $salary_income_type->id());
        }
        $payment_statement->save();

        $balance->set('starting_balance', round($new_balance, 2));
        $balance->save();

        $log_message = $this->t('Update Payment Statement for user @employee_fio.
                                       Period: @period.
                                       Count of @action Unpaid Leave days: @days.', [
                                         '@employee_fio' => $employee_fio,
                                         '@period' => $period_name,
                                         '@days' => $rate['count_unpaid_days'],
                                         '@action' => $is_add_event ? 'added' : 'deleted',
                                       ]);
        $action_log_key = $is_add_event ? 'add_unpaid_leave_for_salary' : 'delete_unpaid_leave_from_salary';
        $this->actionLog->log($action_log_key, $log_message, $this->currentUser->id());
      }
      $old_count_unpaid_days = $rate['count_unpaid_days'];
    }
  }

  /**
   * Get days from range.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $initial_date
   *   Start date.
   * @param int $count_days
   *   Count days in range.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   End date.
   * @param int $employee_id
   *   Employee id.
   *
   * @return array
   *   Array with days dates.
   */
  public function getDaysFromRangeWithoutWeekends(DrupalDateTime $initial_date, int $count_days, DrupalDateTime $end_date, int $employee_id) : array {
    $days = [
      $initial_date->format('Y-m-d') => $initial_date->format('Y-m-d'),
    ];
    $department = $this->getAdminDepartmentByEmployee($employee_id);
    $weekends = $this->vacationsService->getHolidaysAndTransfers($department, clone $initial_date, $end_date);

    for ($i = 1; $i < $count_days; $i++) {
      $mod_initial_date = $initial_date->modify('+1 day');
      $days[$mod_initial_date->format('Y-m-d')] = $mod_initial_date->format('Y-m-d');
    }

    foreach ($days as $day => $date) {
      $date_obj = new DrupalDateTime($date);
      $date_format = $date_obj->format('D');
      if (in_array($date, $weekends['transfers'])) {
        continue;
      }
      if (!in_array($date_format, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'])) {
        unset($days[$day]);
      }
      if (in_array($date, $weekends['holidays'])) {
        unset($days[$day]);
      }
    }

    return $days;
  }

  /**
   * Get rate by period.
   *
   * @param int $employee_id
   *   Employee id.
   * @param string $date
   *   Single date in string format.
   *
   * @return string
   *   Employee's rate from period or employee's agreement currency.
   */
  public function getRateByDate(int $employee_id, string $date) : string {
    $fulltime_sal_agr_assessment_type = $this->termStorage->loadByProperties([
      'vid' => 'assessments_type',
      'machine_name' => self::FULLTIME,
    ]);

    $agreements_ids = $this->agreementStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('sal_agr_agreements_subject', $employee_id)
      ->condition('sal_agr_agreements_start_date', $date, '<=')
      ->condition('sal_agr_assessment_type', current($fulltime_sal_agr_assessment_type)->id())
      ->sort('sal_agr_agreements_start_date', 'DESC')
      ->execute();
    $agreement_id = array_shift($agreements_ids);
    $agreement = $this->agreementStorage->load($agreement_id);
    $rate = NULL;
    $currency = NULL;

    if (!empty($agreement->get('sl_agr_rate_fulltime'))) {
      $rate = $agreement->get('sl_agr_rate_fulltime')->value;
    }
    if (!empty($agreement->get('sal_agr_rate_currency'))) {
      $currency = $agreement->get('sal_agr_rate_currency')->referencedEntities()[0]->get('machine_name')->value;
    }

    if ($currency !== self::USD) {
      $exchange_rate = $this->getExchangeRateForUnpaidLeave($currency, $employee_id, $date);
      if (isset($exchange_rate)) {
        return $rate / $exchange_rate . '$';
      }
      else {
        return $currency;
      }
    }

    return $rate . '$';
  }

  /**
   * Get exchange rate for unpaid leave.
   *
   * @param string $currency
   *   Agreement's currency.
   * @param int $employee_id
   *   Employee id.
   * @param string $date
   *   Date of the day.
   *
   * @return string|null
   *   Exchange rate for non usd rate or null.
   */
  public function getExchangeRateForUnpaidLeave(string $currency, int $employee_id, string $date): ?string {
    $department_id = $this->getAdminDepartmentByEmployee($employee_id);
    $billable_period = $this->getNextPeriodIdByDate($date);
    $currency = taxonomy_term_machine_name_load($currency, 'currency');

    $current_exchange_rate = current($this->nodeStorage->loadByProperties([
      'type' => 'salary_exchange_rate',
      'ser_period' => $billable_period,
      'ser_currency' => $currency->id(),
      'ser_administrative_department' => $department_id,
    ]));

    if (!empty($current_exchange_rate) && !empty($current_exchange_rate->get('ser_rate'))) {
      $exchange_rate = $current_exchange_rate->get('ser_rate')->value;
    }

    return $exchange_rate ?? NULL;
  }

  /**
   * Get days from periods.
   *
   * @param string $start_date
   *   Start leave day.
   * @param string $end_date
   *   End leave day.
   * @param int $employee_id
   *   Employee id.
   *
   * @return array
   *   Array with days from different periods.
   */
  public function getDaysFromPeriods(string $start_date, string $end_date, int $employee_id) : array {
    $days_periods = [];
    $start_date_obj = new DrupalDateTime($start_date);
    $end_date_obj = new DrupalDateTime($end_date);
    $count_days = $end_date_obj->diff($start_date_obj)->days + 1;
    $days = $this->getDaysFromRangeWithoutWeekends(clone $start_date_obj, $count_days, clone $end_date_obj, $employee_id);
    $first_period_id = $this->getPeriodIdByExchangeRateDate(array_key_first($days));
    foreach ($days as $day) {
      $period_id = $this->getPeriodIdByExchangeRateDate($day);
      if ($period_id === $first_period_id) {
        $days_periods['first_period'][$day] = $day;
      }
      else {
        $days_periods['second_period'][$day] = $day;
      }
    }

    return $days_periods;
  }

  /**
   * Get next periodIdByDate.
   *
   * @param string $date
   *   The date of day in string format.
   *
   * @return int
   *   Id of the next period.
   */
  public function getNextPeriodIdByDate(string $date) {
    $current_period_id = $this->getPeriodIdByExchangeRateDate($date);
    $current_period = $this->termStorage->load($current_period_id);
    $current_period_start_date = NULL;
    if (!empty($current_period) && $current_period->get('br_invoice_period')) {
      $current_period_start_date = $current_period->get('br_invoice_period')->value;
    }
    $start_current_period = new DrupalDateTime($current_period_start_date, 'UTC');
    $next_period_date = $start_current_period->modify('+1 month')->format('Y-m-d');
    $query = $this->termStorage->getQuery()
      ->accessCheck(FALSE);
    $query->condition('vid', 'billable_range');
    $query->condition('br_invoice_period.value', $next_period_date, '<=');
    $query->condition('br_invoice_period.end_value', $next_period_date, '>=');
    $next_period_ids = $query->execute();

    return array_shift($next_period_ids);
  }

  /**
   * Get previous periodIdByDate.
   *
   * @param string $date
   *   The date of day in string format.
   *
   * @return int
   *   Id of the next period.
   */
  public function getPreviousPeriodIdByDate(string $date) {
    $current_period_id = $this->getPeriodIdByExchangeRateDate($date);
    $current_period = $this->termStorage->load($current_period_id);
    $current_period_start_date = NULL;
    if (!empty($current_period) && $current_period->get('br_invoice_period')) {
      $current_period_start_date = $current_period->get('br_invoice_period')->value;
    }
    $start_current_period = new DrupalDateTime($current_period_start_date, 'UTC');
    $next_period_date = $start_current_period->modify('-1 month')->format('Y-m-d');
    $query = $this->termStorage->getQuery()
      ->accessCheck(FALSE);
    $query->condition('vid', 'billable_range');
    $query->condition('br_invoice_period.value', $next_period_date, '<=');
    $query->condition('br_invoice_period.end_value', $next_period_date, '>=');
    $next_period_ids = $query->execute();

    return array_shift($next_period_ids);
  }

  /**
   * Checks if there are 'payment statements' of type 'salary'.
   *
   * @param int|string $period_id
   *   ID of the period.
   * @param int|string $executive_department_id
   *   ID of the department.
   *
   * @return bool
   *   True if it exists.
   */
  public function ifSalaryPaymentStatmentsExists($period_id, $executive_department_id) {
    $salary_type_id = taxonomy_term_machine_name_load('st_salary', 'salary_type')->id();
    $payment_statements = $this->nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'payment_statement')
      ->condition('ps_transaction_period', $period_id)
      ->condition('ps_transaction_type', $salary_type_id)
      ->condition('ps_employee.entity:user.u_executive_department', $executive_department_id)
      ->execute();
    return !empty($payment_statements);
  }

  /**
   * Checks if the department exists.
   *
   * @param int|string $department_id
   *   ID of the department.
   * @param int|string $period_id
   *   ID of the period.
   * @param int|string $currency
   *   Currency.
   *
   * @return bool
   *   True if it exists.
   */
  public function ifDepartmentExists($department_id, $period_id, $currency) {
    $exchange_rates_ids = $this->nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'salary_exchange_rate')
      ->condition('ser_administrative_department', $department_id)
      ->condition('ser_period', $period_id)
      ->condition('ser_currency', $currency)
      ->execute();
    return !empty($exchange_rates_ids);
  }

  /**
   * Count vacation days in period.
   *
   * @param int|string $period_id
   *   Salary period id.
   * @param int|string $employee_id
   *   Employee id.
   * @param string $vacation_type
   *   A vacation's type.
   *
   * @return int
   *   Count vacation days.
   */
  public function countVacationDaysInPeriod($period_id, $employee_id, string $vacation_type) : int {
    $count_business_days = 0;

    $period = $this->termStorage->load($period_id);
    if (!empty($period->get('br_invoice_period'))) {
      $period_start_date_formatted = $period->get('br_invoice_period')->value;
      $period_end_date_formatted = $period->get('br_invoice_period')->end_value;
      $period_start_date_obj = new DrupalDateTime($period_start_date_formatted);
      $period_end_date_obj = new DrupalDateTime($period_end_date_formatted);

      $vacation_type_id = taxonomy_term_machine_name_load($vacation_type, 'vacation_types')->id();
      $vacations_ids = $this->nodeStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'vacation')
        ->condition('vacation_type', $vacation_type_id)
        ->condition('vacation_date_from', $period_end_date_formatted, '<=')
        ->condition('vacation_date_to', $period_start_date_formatted, '>=')
        ->condition('vacation_employee', $employee_id)
        ->execute();

      foreach ($vacations_ids as $vacation_id) {
        $vacation = $this->nodeStorage->load($vacation_id);

        $vacation_start_date_obj = new DrupalDateTime($vacation->get('vacation_date_from')->value);
        $start_date_obj = $vacation_start_date_obj;
        if ($period_start_date_obj > $vacation_start_date_obj) {
          $start_date_obj = $period_start_date_obj;
        }

        $vacation_end_date_obj = new DrupalDateTime($vacation->get('vacation_date_to')->value);
        $end_date_obj = $vacation_end_date_obj;
        if ($period_end_date_obj < $vacation_end_date_obj) {
          $end_date_obj = $period_end_date_obj;
        }

        $count_general_days = $end_date_obj->diff($start_date_obj)->days + 1;
        $count_business_days += count($this->getDaysFromRangeWithoutWeekends(clone $start_date_obj, $count_general_days, clone $end_date_obj, $employee_id));
      }
    }

    return $count_business_days;
  }

  /**
   * Count sick days in a period.
   *
   * @param int $period_id
   *   Salary period id.
   * @param int $employee_id
   *   Employee id.
   *
   * @return int
   *   Count sick days.
   */
  public function countSickDaysInPeriod(int $period_id, int $employee_id) : int {
    $start_period = NULL;
    $end_period = NULL;
    $period = $this->termStorage->load($period_id);
    if (!empty($period->get('br_invoice_period'))) {
      $start_period = $period->get('br_invoice_period')->value;
      $end_period = $period->get('br_invoice_period')->end_value;
    }
    $sick_day_type = taxonomy_term_machine_name_load(Vacation::VACATION_TYPE_SICK_DAY, 'vacation_types');

    $sick_day_ids = $this->nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'vacation')
      ->condition('vacation_type', $sick_day_type->id())
      ->condition('vacation_date_from', $start_period, '>=')
      ->condition('vacation_date_to', $end_period, '<=')
      ->condition('vacation_employee', $employee_id)
      ->execute();

    return count($sick_day_ids);
  }

  /**
   * Count Full-time workload hours.
   *
   * @param int $period_id
   *   Salary period id (Billable_range).
   * @param int $employee_id
   *   Employee id.
   *
   * @return int
   *   Full-time workload hours.
   */
  public function getFullTimeWorkloadHours(int $period_id, int $employee_id) {
    $billable_range = $this->termStorage->load($period_id);
    $year = (new DrupalDateTime($billable_range->get('br_invoice_period')->value))->format('Y');
    $user = $this->userStorage->load($employee_id);

    $fulltime_workload_hours = 0;
    if (!empty($user->get('u_executive_department')->target_id)) {
      $calendar_id = current($this->getDepartmentCalendarId($user->get('u_executive_department')->target_id, $year));
      $current_agreement_working_hours = $this->vacationsService->getCurrentSalaryAgreementWorkingHours($employee_id);
      $working_days_in_month = $this->getBusinessDaysInMonthByCalendar($calendar_id, $period_id);
      $fulltime_workload_hours = $working_days_in_month * $current_agreement_working_hours;
    }

    return $fulltime_workload_hours;
  }

  /**
   * Get all currencies without selected.
   *
   * @param array $currencies_to_remove
   *   Array with currencies to remove.
   *
   * @return array
   *   Array with all currencies except selected.
   */
  public function getAllCurrenciesWithoutSelected(array $currencies_to_remove) : array {
    $all_currencies = $this->getCurrencies();

    foreach ($all_currencies as $currency_id => $currency_name) {
      if (array_key_exists($currency_id, $currencies_to_remove)) {
        unset($all_currencies[$currency_id]);
      }
    }

    return $all_currencies;
  }

  /**
   * Get currency of an agreement.
   *
   * @param \Drupal\eck\Entity\EckEntity $agreement
   *   Array with currencies to remove.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   Currency.
   */
  public function getCurrencyByAgreement(EckEntity $agreement) {
    $currency = '';
    if (!empty($agreement->get('sal_agr_rate_currency')->referencedEntities())) {
      $currency = current($agreement->get('sal_agr_rate_currency')->referencedEntities());
    }

    return $currency;
  }

}
