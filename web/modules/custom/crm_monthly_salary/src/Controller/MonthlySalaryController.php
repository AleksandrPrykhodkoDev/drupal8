<?php

namespace Drupal\crm_monthly_salary\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\crm_monthly_salary\MonthlySalaryService;
use Drupal\crm_payments\PaymentService;
use Drupal\crm_statements\PaymentStatementService;
use Drupal\hr_common\Service\UserService;
use Drupal\sales_module\Component\Utility\HtmlExtra;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides StatementController.
 */
class MonthlySalaryController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\crm_payments\PaymentService
   */
  protected $paymentService;

  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\crm_statements\PaymentStatementService.
   *
   * @var \Drupal\crm_statements\PaymentStatementService
   */
  protected $paymentStatementService;

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
   * The Monthly Salary Service.
   *
   * @var \Drupal\crm_monthly_salary\MonthlySalaryService
   */
  protected $monthlySalaryService;

  /**
   * Constructs a MonthlySalaryController object.
   *
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Renderer.
   * @param \Drupal\crm_payments\PaymentService $paymentService
   *   The payment service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\crm_statements\PaymentStatementService $statementService
   *   The payment statement service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Account Proxy Interface.
   * @param \Drupal\hr_common\Service\UserService $user_service
   *   User service.
   * @param \Drupal\crm_monthly_salary\MonthlySalaryService $monthly_salary_service
   *   The Monthly Salary Service.
   */
  public function __construct(
    Renderer $renderer,
    PaymentService $paymentService,
    EntityTypeManagerInterface $entity_type_manager,
    PaymentStatementService $statementService,
    AccountProxyInterface $current_user,
    UserService $user_service,
    MonthlySalaryService $monthly_salary_service) {
    $this->renderer = $renderer;
    $this->paymentService = $paymentService;
    $this->entityTypeManager = $entity_type_manager;
    $this->paymentStatementService = $statementService;
    $this->currentUser = $current_user;
    $this->userService = $user_service;
    $this->monthlySalaryService = $monthly_salary_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('crm_payments.payment_service'),
      $container->get('entity_type.manager'),
      $container->get('crm_statements.statement_service'),
      $container->get('current_user'),
      $container->get('hr_common.user_service'),
      $container->get('crm_monthly_salary.monthly_salary_service')
    );
  }

  /**
   * Show Company payment statement page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array
   *   Render block.
   */
  public function showExchangesRatePage(Request $request): array {
    $view = views_embed_view('salary_s_exchange_rate', 'salary_exchange');
    $selected_billable_period = $request->query->get('period');
    $default_billable_period_tid = $selected_billable_period;
    if (empty($selected_billable_period)) {
      $default_billable_period_tid = $this->monthlySalaryService->getCurrentPeriod();
    }
    $args = [
      'period_pay_period_target_id' => $default_billable_period_tid,
    ];

    $view["#arguments"] = $args;
    $block = $this->renderer->renderRoot($view);
    $links = $this->monthlySalaryService->getPeriodLinks($default_billable_period_tid);

    return [
      '#theme' => 'salary_exchange_rate',
      '#block_data' => $block,
      '#title' => $this->t('Salary exchange rate'),
      '#prev_period_link' => $links['prev_month_link'],
      '#next_period_link' => $links['next_month_link'],
      '#current_period' => $links['current_period'],
    ];
  }

  /**
   * Show add exchange rate form.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render block.
   */
  public function addExchangeRate() {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_monthly_salary.show_exchanges_rate_page');
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()->getForm('Drupal\crm_monthly_salary\Form\AddExchangeRateForm');
  }

  /**
   * Show edit exchange rate form.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render block.
   */
  public function editExchangeRate($nid = NULL) {
    if (!HtmlExtra::getIsAjax()) {
      $url_object = Url::fromRoute('crm_monthly_salary.show_exchanges_rate_page');
      $url = $url_object->toString();
      return new RedirectResponse($url);
    }
    return $this->formBuilder()->getForm('Drupal\crm_monthly_salary\Form\EditExchangeRateForm', $nid);
  }

}
