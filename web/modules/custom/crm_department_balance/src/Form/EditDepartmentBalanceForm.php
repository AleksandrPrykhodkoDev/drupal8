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
 * Class EditDepartmentBalanceForm.
 */
class EditDepartmentBalanceForm extends FormBase {

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
    return 'edit_department_balance';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    $department_balance_entity = $this->entityTypeManager->getStorage('node')->load($nid);
    $department_balance_name = $department_balance_entity->get('db_balance_name')->value;
    $department_balance_amount = $department_balance_entity->get('db_amount')->value;
    $department_balance_currency = $department_balance_entity->get('db_currency')->target_id;
    $balance_transactions = $this->departmentBalanceService->getBalanceTransactions($nid);

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['balance_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Balance name'),
      '#default_value' => $department_balance_name,
      '#required' => TRUE,
      '#suffix' => '<div class="exchange-rate-select">',
    ];

    $form['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#default_value' => $department_balance_amount,
      '#required' => TRUE,
    ];

    $form['currency'] = [
      '#type' => 'select',
      '#options' => $this->departmentBalanceService->getCurrencies(),
      '#default_value' => $department_balance_currency,
      '#suffix' => '</div>',
    ];

    if (!empty($balance_transactions)) {
      $form['amount']['#disabled'] = TRUE;
      $form['currency']['#disabled'] = TRUE;
    }

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
    $all_department_balance = $this->departmentBalanceService->getAllDepartmentBalance();
    $department_balance_entity = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('nid'));
    $balance_name['current'] = $department_balance_entity->get('db_balance_name')->value;
    $balance_name['new'] = $form_state->getValue('balance_name');
    $balance = $form_state->getValue('amount');

    if ($balance_name['new'] != $balance_name['current']) {
      if (in_array($balance_name['new'], $all_department_balance)) {
        $form_state->setErrorByName(
          'department_balance', $this->t('A balance with this name exists, please change the name')
        );
      }
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
    $department_balance_entity = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('nid'));
    $old_currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($department_balance_entity->get('db_currency')->target_id);
    $old_currency_title = $old_currency->get('name')->value;
    $new_currency = $this->entityTypeManager->getStorage('taxonomy_term')->load($form_state->getValue('currency'));
    $new_currency_title = $new_currency->get('name')->value;
    $balance_transactions = $this->departmentBalanceService->getBalanceTransactions($form_state->getValue('nid'));

    $balance_id = $department_balance_entity->id();
    $current_date = new DrupalDateTime('now', 'UTC');
    $date = $current_date->format('Y-m-d');
    $comment = $this->t('The balance has been changed from
    @old_amount @old_currency to
    @new_amount @new_currency', [
      '@old_amount' => $department_balance_entity->get('db_amount')->value,
      '@new_amount' => $balance,
      '@old_currency' => $old_currency_title,
      '@new_currency' => $new_currency_title,
    ]);

    $log_message = $this->t('Department Balance - @new_name was updated:
    Amount from @old_amount to @new_amount,
    Balance name from @old_name to @new_name,
    Currency from @old_currency to @new_currency', [
      '@old_amount' => $department_balance_entity->get('db_amount')->value,
      '@new_amount' => $balance,
      '@old_name' => $department_balance_entity->get('db_balance_name')->value,
      '@new_name' => $balance_name,
      '@old_currency' => $old_currency_title,
      '@new_currency' => $new_currency_title,
    ]);

    $old_balance = '';
    if (!empty($department_balance_entity->get('db_amount')->value)) {
      $old_balance = (float) $department_balance_entity->get('db_amount')->value;
    }
    $old_balance_currency = '';
    if (!empty($department_balance_entity->get('db_currency')->target_id)) {
      $old_balance_currency = $department_balance_entity->get('db_currency')->target_id;
    }

    $department_balance_entity->set('db_amount', $balance);
    $department_balance_entity->set('db_balance_name', $balance_name);
    $department_balance_entity->set('db_currency', $balance_currency);
    $department_balance_entity->save();

    if (!empty($balance_transactions)) {
      if ($old_balance != $balance || $old_balance_currency != $balance_currency) {
        $balance_transaction = $this->entityTypeManager->getStorage('node')
          ->create([
            'type' => 'department_view_details_balance',
            'title' => 'Department transaction for ' . $balance_id,
            'uid' => 1,
            'dvd_amount_of_payment' => '-',
            'dvd_balance' => $balance_id,
            'dvd_date' => $date,
            'dvd_transaction_description' => $comment,
            'dvd_is_system_transaction' => TRUE,
          ]);
        $balance_transaction->save();
      }
    }

    $this->actionLog->log('edit_department_balance', $log_message, $this->currentUser->id());
    $this->messenger()
      ->addStatus($this->t('Balance was successfully updated'));
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
