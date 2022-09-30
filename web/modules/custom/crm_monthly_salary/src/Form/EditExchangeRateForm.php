<?php

namespace Drupal\crm_monthly_salary\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\hr_common\Ajax\CloseFormCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\hr_common\Enum\Currency;

/**
 * Class AddDepartmentForm.
 */
class EditExchangeRateForm extends FormBase {

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
   * Monthly Salary Service.
   *
   * @var \Drupal\crm_monthly_salary\MonthlySalaryService
   */
  protected $monthlySalaryService;

  /**
   * Drupal\crm_statements\PaymentStatementService.
   *
   * @var \Drupal\crm_statements\PaymentStatementService
   */
  protected $paymentStatementService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    $instance->actionLog = $container->get('crm_action_log.log');
    $instance->currentUser = $container->get('current_user');
    $instance->monthlySalaryService = $container->get('crm_monthly_salary.monthly_salary_service');
    $instance->paymentStatementService = $container->get('crm_statements.statement_service');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_exchange_rate';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    $salary_exchange_rate = $this->entityTypeManager->getStorage('node')->load($nid);
    $currencies_to_remove = [];
    $usd_currency_id = taxonomy_term_machine_name_load(Currency::USD, 'currency')->id();
    $currencies_to_remove[$usd_currency_id] = $usd_currency_id;

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['title'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h3>Edit exchange rate</h3>'),
      '#suffix' => '<div class="exchange-rate-select">',
    ];

    $form['month'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#required' => TRUE,
      '#options' => $this->paymentStatementService->getMonthOptions(),
      '#default_value' => $this->paymentStatementService->getYearMonthFromPeriod()['month'],
    ];

    $form['year'] = [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#required' => TRUE,
      '#options' => $this->paymentStatementService->getYearsForIncomeTransaction(),
      '#default_value' => $this->paymentStatementService->getYearMonthFromPeriod()['year'],
      '#suffix' => '</div><div class="exchange-rate-select">',
    ];

    $form['rate'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exchange rate'),
      '#description' => $this->t('You should use number, e.g 2.56'),
      '#required' => TRUE,
      '#default_value' => $salary_exchange_rate->get('ser_rate')->value,
    ];

    $form['currency'] = [
      '#type' => 'select',
      '#options' => $this->monthlySalaryService->getAllCurrenciesWithoutSelected($currencies_to_remove),
      '#required' => TRUE,
      '#empty_option' => '- None -',
      '#default_value' => $salary_exchange_rate->get('ser_currency')->target_id,
      '#suffix' => '</div>',
    ];

    $form['admin_department'] = [
      '#type' => 'select',
      '#title' => $this->t('Administrative Department'),
      '#options' => $this->paymentStatementService->getAdminDepartments(),
      '#empty_option' => '- None -',
      '#required' => TRUE,
      '#default_value' => $salary_exchange_rate->get('ser_administrative_department')->target_id,
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
    $rate = $form_state->getValue('rate');
    $currency = $form_state->getValue('currency');
    $full_date = $form_state->getValue('year') . '-' . $form_state->getValue('month') . '-01';
    $period_id = $this->monthlySalaryService->getPeriodIdByExchangeRateDate($full_date);

    $previous_period_id = $this->monthlySalaryService->getPreviousPeriodIdByDate($full_date);
    if ($this->monthlySalaryService->ifSalaryPaymentStatmentsExists($previous_period_id, $form_state->getValue('admin_department'))) {
      $form_state->setErrorByName('add_exchange_rate', $this->t('There are salary payment statements for this period!'));
    }

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('type', 'salary_exchange_rate');
    $query->condition('nid', $form_state->getValue('nid'), '!=');
    $query->condition('ser_administrative_department', $form_state->getValue('admin_department'));
    $query->condition('ser_period', $period_id);
    $query->condition('ser_currency', $form_state->getValue('currency'));
    $exchange_rates_ids = $query->execute();

    if (count($exchange_rates_ids) > 0) {
      $form_state->setErrorByName('add_exchange_rate', $this->t('The Exchange Rate for this administrative department with this currency in this period is already exists!'));
    }
    if (preg_match('/[0-9]{5}/', $full_date) !== 0) {
      $form_state->setErrorByName('add_exchange_rate', $this->t('You should enter the correct year!'));
    }
    if (is_string($rate) && str_contains($rate, ',')) {
      $form_state->setErrorByName(
        'add_exchange_rate', $this->t('The fractional mark must be a point(.)')
      );
    }
    if (!is_numeric($rate)) {
      $form_state->setErrorByName(
        'add_exchange_rate', $this->t('The Amount field data is not a number!')
      );
    }
    if ($rate <= 0) {
      $form_state->setErrorByName(
        'add_exchange_rate', $this->t('The Exchange Rate field: value must be greater than 0!')
      );
    }
    if (empty($currency)) {
      $form_state->setErrorByName(
        'add_exchange_rate', $this->t('The Currency field data is empty!')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $full_date = $form_values['year'] . '-' . $form_values['month'] . '-01';
    $period_id = $this->monthlySalaryService->getPeriodIdByExchangeRateDate($full_date);
    $format_date = DrupalDateTime::createFromFormat('Y-m-d', $full_date);
    $currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_values['currency']);
    $department = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_values['admin_department']);

    $salary_exchange_rate = $this->entityTypeManager->getStorage('node')->load($form_values['nid']);
    $salary_exchange_rate->set('ser_administrative_department', $form_values['admin_department']);
    $salary_exchange_rate->set('ser_currency', $form_values['currency']);
    $salary_exchange_rate->set('ser_date', $full_date);
    $salary_exchange_rate->set('ser_rate', $form_values['rate']);
    $salary_exchange_rate->set('ser_period', $period_id);
    $salary_exchange_rate->save();

    $message = $this->t('Exchange rate for @department with currency - @currency on @date was updated.', [
      '@department' => $department->get('name')->value,
      '@currency' => $currency->get('name')->value,
      '@date' => $format_date->format('d-m-Y'),
    ]);
    $this->actionLog->log('edit_salary_exchange_rate', $message, $this->currentUser->id());
    $this->messenger()->addStatus($this->t('Exchange rate was successfully updated'));
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
