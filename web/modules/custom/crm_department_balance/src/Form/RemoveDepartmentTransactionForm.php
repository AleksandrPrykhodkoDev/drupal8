<?php

namespace Drupal\crm_department_balance\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hr_common\Ajax\CloseFormCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RemoveDepartmentTransactionForm class.
 */
class RemoveDepartmentTransactionForm extends FormBase {

  use StringTranslationTrait;

  const DOLLARS = 'dollars';

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The renderer service instance.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->actionLog = $container->get('crm_action_log.log');
    $instance->currentUser = $container->get('current_user');
    $instance->renderer = $container->get('renderer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'remove_department_transaction';
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
      '#title' => $this->t('Are you sure you want to delete this transaction?'),
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigerring_element = $form_state->getTriggeringElement();
    $department_transaction_amount = 0;
    $department_balance_amount = 0;

    if ($trigerring_element['#name'] !== 'cancel') {
      $department_transaction = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('nid'));
      // Here we get amount from transaction.
      if (!empty($department_transaction->get('dvd_amount_of_payment'))) {
        $department_transaction_amount = $department_transaction->get('dvd_amount_of_payment')->value;
      }
      // Here we load department balance amount.
      if (!empty($department_transaction->get('dvd_balance'))) {
        $department_balance = $department_transaction->get('dvd_balance')->referencedEntities()[0];
        $department_balance_title = $department_balance->get('db_balance_name')->value;
        $department_balance_amount = $department_balance->get('db_amount')->value;
      }
      // Here we check type of transaction and then edit department balance amount.
      if ($department_transaction->get('dvd_is_income')->value == 1) {
        $new_department_balance_amount = $department_balance_amount - $department_transaction_amount;
      }
      else {
        $new_department_balance_amount = $department_balance_amount + $department_transaction_amount;
      }
      $department_balance->set('db_amount', $new_department_balance_amount);
      $department_balance->save();
      $department_transaction_title = $department_transaction->get('title')->value;
      $department_transaction->delete();
      $current_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      $user_fio = '';
      if (!empty($current_user->get('u_applicants_fio')->value)) {
        $user_fio = $current_user->get('u_applicants_fio')->value;
      }
      $log_message = $this->t('Transaction - @transaction_name was deleted by @user_name from Department Balance - @balance_name', [
        '@transaction_name' => $department_transaction_title,
        '@balance_name' => $department_balance_title,
        '@user_name' => $user_fio,
      ]);
      $this->actionLog->log('remove_transaction_from_department_balance', $log_message, $this->currentUser->id());
      $this->messenger()->addStatus($this->t('Transaction was successfully deleted!'));
    }
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
