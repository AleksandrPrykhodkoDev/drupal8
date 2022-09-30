<?php

namespace Drupal\crm_monthly_salary\Plugin\views\field;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\hr_common\Service\UserService;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\staff_module\StaffInfoService;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("exchange_rate_actions")
 */
class ExchangeRateActions extends FieldPluginBase {

  /**
   * Staff info service.
   *
   * @var \Drupal\staff_module\StaffInfoService
   */
  protected $staffInfoService;

  /**
   * The entity type manager service instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The User Service.
   *
   * @var \Drupal\hr_common\Service\UserService
   */
  protected $userService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    StaffInfoService $staff_info_service,
    AccountProxyInterface $account_proxy,
    UserService $user_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->staffInfoService = $staff_info_service;
    $this->currentUser = $account_proxy;
    $this->userService = $user_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('staff_module.staff_info_service'),
      $container->get('current_user'),
      $container->get('hr_common.user_service')
    );
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
    $nid = $values->nid;
    $admin_department_id = $values->_entity->get('ser_administrative_department')->target_id;
    $result['edit'] = [
      '#type' => 'markup',
      '#markup' => '<div class="disabled btn btn-xs btn-warning fa fa-pencil-square-o round-b-size mg-l_5 mg-r_5"></div>',
    ];

    if ($this->userService->checkIsDepartmentHead($this->currentUser->id(), $admin_department_id) || in_array('ceo', $this->currentUser->getRoles())) {
      $result['edit'] = [
        '#type' => 'link',
        '#title' => '',
        '#url' => Url::fromRoute('crm_monthly_salary.edit_exchange_rate', [
          'nid' => $nid,
        ]),
        '#attributes' => [
          'class' => 'use-ajax btn btn-xs btn-warning fa fa-pencil-square-o round-b-size mg-l_5 mg-r_5',
          'data-dialog-type' => 'modal',
        ],
      ];
    }

    return $result;
  }

}
