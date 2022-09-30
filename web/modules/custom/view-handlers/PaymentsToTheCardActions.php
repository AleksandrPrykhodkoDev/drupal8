<?php

namespace Drupal\crm_payments\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\crm_payments\PaymentService;
use Drupal\Core\Session\AccountProxy;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("payments_to_the_card_actions")
 */
class PaymentsToTheCardActions extends FieldPluginBase {

  /**
   * The entity type manager service instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * The payment service.
   *
   * @var \Drupal\crm_payments\PaymentService
   */
  protected $paymentService;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('crm_payments.payment_service'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    PaymentService $paymentService,
    AccountProxy $currentUser
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->payment_service = $paymentService;
    $this->currentUser = $currentUser;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We don't need to modify query for this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $result = [];
    $pay_period_id = $values->id;
    $payments_ids = $this->payment_service->checkPaymentsInPayPeriod($pay_period_id);
    $current_user_roles = $this->currentUser->getRoles();
    $period_pay_status = $this->entityTypeManager->getStorage('period_pay')->load($pay_period_id)->get('period_pay_status')->target_id;
    $waiting_for_approve_period_pay_status = taxonomy_term_machine_name_load('waiting_for_approve_period_pay_status', 'period_pay_status');
    $done_period_pay_status = taxonomy_term_machine_name_load('done_period_pay_status', 'period_pay_status');

    $url_add = '';
    if ($period_pay_status !== $done_period_pay_status->id()) {
      $url_add = Url::fromRoute('crm_payments.add_payment', ['pay_period_id' => $pay_period_id]);
    }
    $result['add_payment'] = [
      '#type' => 'link',
      '#url' => $url_add,
      '#title' => $this->t('Add Transaction'),
      '#attributes' => [
        'class' => 'use-ajax btn-info btn approve-btn',
        'data-dialog-type' => 'modal',
      ],
    ];

    $decline_period_pay_status = taxonomy_term_machine_name_load('decline_period_pay_status', 'period_pay_status');

    if (in_array('administrative_head', $current_user_roles) || in_array('ceo', $current_user_roles)) {
      if ($period_pay_status !== $decline_period_pay_status->id() && $period_pay_status !== $done_period_pay_status->id()) {
        $url_recount = (!$payments_ids) ? '' : Url::fromRoute('crm_payments.recount_payment', ['pay_period_id' => $values->id]);

        $result['recount_payment'] = [
          '#type' => 'link',
          '#url' => $url_recount,
          '#title' => $this->t('Send for recount'),
          '#attributes' => [
            'class' => 'use-ajax btn-info btn send-recount-btn',
            'data-dialog-type' => 'modal',
          ],
        ];
      }
    }

    if (in_array('ceo', $current_user_roles) && $period_pay_status !== $done_period_pay_status->id()) {
      $url_ready_to_pay = (!$payments_ids) ? '' : Url::fromRoute('crm_payments.ready_to_pay', ['pay_period_id' => $values->id]);
      $result['ready_to_pay'] = [
        '#type' => 'link',
        '#url' => $url_ready_to_pay,
        '#title' => $this->t('Ready to pay'),
        '#attributes' => [
          'class' => 'use-ajax btn btn-sm btn-success ready-to-pay-btn',
          'data-dialog-type' => 'modal',
        ],
      ];
    }

    $approve_period_pay_status = taxonomy_term_machine_name_load('approve_period_pay_status', 'period_pay_status');
    $url_approve = '';
    if ($period_pay_status !== $done_period_pay_status->id() && $payments_ids) {
      $url_approve = Url::fromRoute('crm_payments.approve_to_pay', ['pay_period_id' => $pay_period_id]);
    }

    if (in_array('administrative_head', $current_user_roles)) {
      if ($period_pay_status !== $approve_period_pay_status->id()) {
        $result['approve_to_pay'] = [
          '#type' => 'link',
          '#url' => $url_approve,
          '#title' => $this->t('Approve to pay'),
          '#attributes' => [
            'class' => 'use-ajax btn btn-success approve-pay-btn',
            'data-dialog-type' => 'modal',
          ],
        ];
      }
    }

    $url_return = '';
    $new_period_pay_status = taxonomy_term_machine_name_load('new_period_pay_status', 'period_pay_status');
    if ($period_pay_status !== $done_period_pay_status->id() && $payments_ids) {
      $url_return = Url::fromRoute('crm_payments.return_payment', ['pay_period_id' => $pay_period_id]);
    }

    if (in_array('accountant', $current_user_roles)) {
      if ($period_pay_status !== $new_period_pay_status->id()
        && $period_pay_status !== $approve_period_pay_status->id()
        && $period_pay_status !== $decline_period_pay_status->id()) {
        $result['return_payment'] = [
          '#type' => 'link',
          '#url' => $url_return,
          '#title' => $this->t('Return Transaction'),
          '#attributes' => [
            'class' => 'use-ajax btn btn-success return-payment-btn',
            'data-dialog-type' => 'modal',
          ],
        ];
      }
      elseif ($period_pay_status !== $waiting_for_approve_period_pay_status->id()
        && $period_pay_status !== $done_period_pay_status->id()) {
        $url_submit = (!$payments_ids) ? '' : Url::fromRoute('crm_payments.payment_for_approval', ['pay_period_id' => $values->id]);
        $result['submit_for_approval'] = [
          '#type' => 'link',
          '#url' => $url_submit,
          '#title' => $this->t('Submit for approval'),
          '#attributes' => [
            'class' => 'use-ajax btn btn-sm btn-success submit-for-approval-btn',
            'data-dialog-type' => 'modal',
          ],
        ];
      }
    }

    return $result;
  }

}
