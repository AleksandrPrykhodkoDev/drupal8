<?php

namespace Drupal\crm_department_balance\Plugin\views\field;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Render\Markup;
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
 * @ViewsField("department_details_balance_description")
 */
class ViewDetailsBalanceDescriptionField extends FieldPluginBase {

  /**
   * The entity type manager service instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
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
    $nid = $values->nid;
    $create_by = '';
    $transaction_for = '';

    $department_transaction = $this->entityTypeManager->getStorage('node')
      ->load($nid);
    $transaction_description_markup = NULL;
    if ($department_transaction->get('dvd_transaction_description')->value) {
      $transaction_description = $department_transaction->get('dvd_transaction_description')->value;
      $transaction_description_markup = Markup::create($transaction_description);
    }
    $is_system_transaction = FALSE;
    if (!empty($values->_entity->get('dvd_is_system_transaction')->value)) {
      $is_system_transaction = $values->_entity->get('dvd_is_system_transaction')->value;
      $create_by = 'System transaction';
    }

    if (!$is_system_transaction) {
      if ($department_transaction->get('dvd_admin_departments')->target_id) {
        $admin_department_id = $department_transaction->get('dvd_admin_departments')->target_id;
        $admin_department = $this->entityTypeManager->getStorage('taxonomy_term')
          ->load($admin_department_id);
        $admin_department_title = $admin_department->get('name')->value;
        $transaction_for = 'Administrative department: ' . $admin_department_title;
      }
      if ($department_transaction->get('dvd_departments')->target_id) {
        $department_id = $department_transaction->get('dvd_departments')->target_id;
        $department = $this->entityTypeManager->getStorage('taxonomy_term')
          ->load($department_id);
        $department_title = $department->get('name')->value;
        $transaction_for = 'Department: ' . $department_title;
      }
      if ($department_transaction->get('dvd_staff')->target_id) {
        $staff_id = $department_transaction->get('dvd_staff')->target_id;
        $staff = $this->entityTypeManager->getStorage('user')->load($staff_id);
        $staff_fio = $staff->get('u_applicants_fio')->value;
        $transaction_for = 'Staff: ' . $staff_fio;
      }
    }

    return [
      '#theme'           => 'department_balance_description',
      '#create_by'       => $create_by,
      '#transaction_for' => $transaction_for,
      '#comment'         => $transaction_description_markup,
    ];
  }

}
