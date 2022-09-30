<?php

namespace Drupal\crm_department_balance\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\hr_common\Ajax\CloseFormCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddDepartmentBalanceForm.
 */
class AddDepartmentBalanceForm extends FormBase {

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
    return 'add_department_balance';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['balance_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Balance name'),
      '#required' => TRUE,
      '#suffix' => '<div class="exchange-rate-select">',
    ];

    $form['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#required' => TRUE,
    ];

    $form['currency'] = [
      '#type' => 'select',
      '#options' => $this->departmentBalanceService->getCurrencies(),
      '#suffix' => '</div>',
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
    $balance = $form_state->getValue('amount');
    $all_department_balance = $this->departmentBalanceService->getAllDepartmentBalance();
    $balance_name = $form_state->getValue('balance_name');

    if (!empty($all_department_balance) && in_array($balance_name, $all_department_balance)) {
      $form_state->setErrorByName(
        'department_balance', $this->t('A balance with this name exists, please change the name')
      );
    }
    if (is_string($balance) && str_contains($balance, ',')) {
      $form_state->setErrorByName(
        'department_balance', $this->t('The fractional mark must be a point(.)')
      );
    }
    if (!is_numeric($balance)) {
      $form_state->setErrorByName(
        'department_balance', $this->t('The Department balance field data is not a number!')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $balance = (float) $form_state->getValue('amount');
    $balance_name = $form_state->getValue('balance_name');
    $balance_currency = $form_state->getValue('currency');

    $starting_balance = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'department_balance',
      'title' => $balance_name,
      'db_amount' => $balance,
      'db_balance_name' => $balance_name,
      'db_currency' => $balance_currency,
    ]);
    $starting_balance->save();

    $message = $this->t('New Department Balance - @name was created', [
      '@name' => $balance_name,
    ]);
    $this->actionLog->log('add_department_balance', $message, $this->currentUser->id());
    $this->messenger()
      ->addStatus($this->t('Balance was successfully added'));
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
