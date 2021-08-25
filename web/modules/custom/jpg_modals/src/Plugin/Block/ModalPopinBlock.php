<?php

namespace Drupal\jpg_modals\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a modal popin block.
 *
 * @Block(
 *   id = "jpg_modal_popin_block",
 *   admin_label = @Translation("JPG Modals - Popin block"),
 * )
 */
class ModalPopinBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The admin context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  private $adminContext;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ModalPopinBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The admin context.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AdminContext $admin_context,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->adminContext = $admin_context;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.admin_context'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'popin_enabled' => TRUE,
      'popin_times_to_show' => 5,
      'popin_page_manager_variant' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['popin_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config['popin_enabled'],
    ];

    $form['popin_times_to_show'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Times to show the modal'),
      '#default_value' => $config['popin_times_to_show'],
      '#required' => TRUE,
    ];

    $options = [];
    $variants = $this->entityTypeManager->getStorage('page_variant')
      ->loadByProperties();
    /** @var \Drupal\page_manager\Entity\PageVariant $variant */
    foreach ($variants as $variant) {
      $options[$variant->id()] = implode(': ', [
        $variant->getPage()->label(),
        $variant->label(),
      ]);
    }

    $form['popin_page_manager_variant'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Page manager variant'),
      '#description' => 'Page manager variant to render',
      '#default_value' => $config['popin_page_manager_variant'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $values = $form_state->getValues();
    $this->configuration['popin_enabled'] = $values['popin_enabled'];
    $this->configuration['popin_times_to_show'] = $values['popin_times_to_show'];
    $this->configuration['popin_page_manager_variant'] = $values['popin_page_manager_variant'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $config = $this->getConfiguration();

    // Welcome popin modal.
    if ($config['popin_enabled'] &&
      !empty($config['popin_page_manager_variant']) &&
      !$this->adminContext->isAdminRoute()) {

      $entity = $this->entityTypeManager->getStorage('page_variant')
        ->load($config['popin_page_manager_variant']);
      if (!empty($entity)) {
        $build = [
          '#theme' => 'popin',
          '#content' => $this->entityTypeManager->getViewBuilder('page_variant')
            ->view($entity),
          '#attached' => [
            'library' => ['jpg_modals/modals_popin'],
            'drupalSettings' => [
              'jpg_modals' => [
                'popin' => [
                  'enabled' => $config['popin_enabled'],
                  'times_to_show' => $config['popin_times_to_show'],
                  'is_logged' => $this->currentUser->isAuthenticated(),
                ],
              ],
            ],
          ],
        ];
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user.roles:anonymous']);
  }

}
