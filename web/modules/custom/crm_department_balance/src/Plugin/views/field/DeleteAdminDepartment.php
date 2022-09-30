<?php

namespace Drupal\crm_department_balance\Plugin\views\field;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a custom field for Department Balance.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("delete_admin_department")
 */
class DeleteAdminDepartment extends FieldPluginBase {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
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
    AccountProxyInterface $account_proxy) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $account_proxy;
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
    if ($values->node__db_admin_departments_db_admin_departments_target_id) {
      $target_id = $values->node__db_admin_departments_db_admin_departments_target_id;

      $result['remove'] = [
        '#type' => 'link',
        '#title' => '',
        '#url' => Url::fromRoute('crm_department_balance.remove_admin_department_balance', [
          'nid' => $nid,
          'target_id' => $target_id,
        ]),
        '#attributes' => [
          'class' => 'use-ajax btn btn-xs btn-danger fa fa-trash round-b-size mg-l_5 mg-r_5',
          '#title' => 'Remove',
          'data-dialog-type' => 'modal',
        ],
      ];
    }

    return $result;
  }

}
