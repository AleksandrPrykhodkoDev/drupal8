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
 * Class RemoveDepartmentBalanceForm.
 */
class RemoveDepartmentBalanceForm extends FormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['help'] = [
      '#type' => 'item',
      '#title' => $this->t('Are you sure you want to delete this Balance?'),
    ];

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'remove-btns',
        ],
      ],
      '#prefix' => '<div>',
      '#suffix' => '</div>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => 'Yes',
      "#attributes" => [
        'class' => ['btn', 'btn-success'],
      ],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#name' => 'cancel',
      '#value' => 'Cancel',
      "#attributes" => [
        'class' => ['btn', 'btn-danger'],
      ],
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
    $balance_id = $form_state->getValue('nid');
    $balance_transactions = $this->departmentBalanceService->getBalanceTransactions($balance_id);

    if ($balance_transactions) {
      $form_state->setErrorByName(
        'department_balance', $this->t('You cannot delete this balance as there are active transactions there!')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigerring_element = $form_state->getTriggeringElement();
    if ($trigerring_element['#name'] !== 'cancel') {
      $department_balance = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('nid'));
      $department_balance_title = $department_balance->get('db_balance_name')->value;
      $department_balance->delete();
      $log_message = $this->t('Department Balance - @name was deleted', [
        '@name' => $department_balance_title,
      ]);
      $this->actionLog->log('remove_department_balance', $log_message, $this->currentUser->id());
      $this->messenger()->addStatus($this->t('Balance was successfully deleted'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $trigerring_element = $form_state->getTriggeringElement();
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

    if (empty($errors) || $trigerring_element['#name'] === 'cancel') {
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
