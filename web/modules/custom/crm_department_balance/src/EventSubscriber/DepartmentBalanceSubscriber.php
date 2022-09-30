<?php

namespace Drupal\crm_department_balance\EventSubscriber;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\hook_event_dispatcher\Event\Theme\ThemeEvent;
use Drupal\hook_event_dispatcher\Event\Views\ViewsQueryAlterEvent;
use Drupal\hook_event_dispatcher\HookEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hook_event_dispatcher\Event\Preprocess\ViewFieldPreprocessEvent;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\staff_module\StaffInfoService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class DepartmentBalanceSubscriber.
 */
class DepartmentBalanceSubscriber implements EventSubscriberInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ModuleHandlerInterface $module_handler
  ) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      HookEventDispatcherInterface::THEME => 'theme',
    ];
  }

  /**
   * Hook theme.
   *
   * @param \Drupal\hook_event_dispatcher\Event\Theme\ThemeEvent $event
   *   The event.
   */
  public function theme(ThemeEvent $event) {
    $path = $this->moduleHandler->getModule('crm_department_balance')->getPath() . '/templates';

    $event->addNewThemes([
      'department_balance' => [
        'variables' => [
          'block' => NULL,
          'is_ceo' => NULL,
          'url_add_balance' => NULL,
        ],
        'path' => $path,
      ],
      'department_balance_details' => [
        'variables' => [
          'balance_name' => NULL,
          'admin_departments_names' => NULL,
          'departments_names' => NULL,
          'staff_names' => NULL,
          'total_balance' => NULL,
          'currency_balance_value' => NULL,
          'is_ceo' => NULL,
          'render_view' => NULL,
          'url_add_income_transaction' => NULL,
          'url_add_expense_transaction' => NULL,
          'url_balance_setting' => NULL,
        ],
        'path' => $path,
      ],
      'balance_setting' => [
        'variables' => [
          'block_admin_departments' => NULL,
          'block_departments' => NULL,
          'block_staff' => NULL,
          'department_balance_title' => NULL,
          'url_add_admin_department' => NULL,
          'url_add_department' => NULL,
          'url_add_staff' => NULL,
          'url_view_details' => NULL,
        ],
        'path' => $path,
      ],
      'department_balance_description' => [
        'variables' => [
          'create_by'       => '',
          'transaction_for' => '',
          'comment'         => '',
        ],
        'path' => $path,
      ]
    ]);
  }

}
