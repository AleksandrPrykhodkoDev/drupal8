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
 * Class AdminDepartmentSettingsForm.
 */
class AdminDepartmentSettingsForm extends FormBase {

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
    return 'admin_department_setting';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {

    $form['balance_id'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['admin_departments'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#size' => 5,
      '#title' => $this->t('Administrative Department'),
      '#options' => $this->departmentBalanceService->getDepartmentSettings($nid, TRUE),
      '#description' => $this->t('Select a department to link it to the balance'),
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $balance_id = $form_state->getValue('balance_id');
    $admin_departments_input = $form_state->getValue('admin_departments');
    $department_balance = $this->entityTypeManager->getStorage('node')->load($balance_id);
    $admin_departments_balance = $this->departmentBalanceService->getDepartmentsInBalance($balance_id, TRUE);
    $admin_departments = $admin_departments_input + array_flip($admin_departments_balance);
    $department_balance->set('db_admin_departments', $admin_departments);
    $department_balance->save();
    $log_message = $this->t('Administrative departments were added to Department Balance - @name', [
      '@name' => $department_balance->get('db_balance_name')->value,
    ]);
    $this->actionLog->log('add_admin_department_to_department_balance', $log_message, $this->currentUser->id());
    $this->messenger()
      ->addStatus($this->t('Admin departments for balance was successfully added'));
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
