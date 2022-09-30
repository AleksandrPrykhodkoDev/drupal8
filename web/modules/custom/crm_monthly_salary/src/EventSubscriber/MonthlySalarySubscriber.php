<?php

namespace Drupal\crm_monthly_salary\EventSubscriber;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\hook_event_dispatcher\Event\Theme\ThemeEvent;
use Drupal\hook_event_dispatcher\Event\Views\ViewsQueryAlterEvent;
use Drupal\hook_event_dispatcher\HookEventDispatcherInterface;
use Drupal\staff_module\VacationsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hook_event_dispatcher\Event\Preprocess\ViewFieldPreprocessEvent;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\staff_module\StaffInfoService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class MonthlySalarySubscriber.
 */
class MonthlySalarySubscriber implements EventSubscriberInterface {

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
    $path = $this->moduleHandler->getModule('crm_monthly_salary')->getPath() . '/templates';

    $event->addNewThemes([
      'salary_exchange_rate' => [
        'variables' => [
          'block_data' => NULL,
          'title' => NULL,
          'prev_period_link' => NULL,
          'next_period_link' => NULL,
          'current_period' => NULL,
        ],
        'path' => $path,
      ],
    ]);
  }

}
