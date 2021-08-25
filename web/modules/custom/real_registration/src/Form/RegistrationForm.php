<?php

namespace Drupal\real_registration\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\rng\RegistrantFactoryInterface;
use Drupal\rng_contact\Entity\RngContact;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for registrations.
 */
class RegistrationForm extends ContentEntityForm {

  /**
   * Provides messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The registrant factory.
   *
   * @var \Drupal\rng\RegistrantFactoryInterface
   */
  protected $registrantFactory;

  /**
   * The Route Match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a LibraryItemForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\rng\RegistrantFactoryInterface $registrant_factory
   *   The registrant factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    MessengerInterface $messenger,
    RegistrantFactoryInterface $registrant_factory,
    RouteMatchInterface $route_match
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->messenger = $messenger;
    $this->registrantFactory = $registrant_factory;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('rng.registrant.factory'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $registration = $this->getEntity();
    $event = $registration->getEvent();

    $contact = RngContact::create(['type' => 'participant']);
    $form_state->set('contact', $contact);

    $form['into'] = [
      '#markup' => $this->t('To register for the «%label» event, please complete the following form :',
        ['%label' => $event->label()]
      ),
      '#weight' => -10,
    ];

    $display = EntityFormDisplay::collectRenderDisplay($contact, 'default');
    $display->buildForm($contact, $form, $form_state);

    $form['outro'] = [
      '#markup' => '<p><em><sup>' . $this->t("You must be at least 16 years old to submit your registration request. Real Campus by L'Oréal uses your personal data to manage your registration, for analyzes and statistical studies and, if you have consented, to send you personalized communications. For more information on how we use your personal data, please see our Privacy Policy. Use of this site is governed by our Terms of Service.") . '</sup></em></p>',
      '#weight' => 10,
    ];
    // Add Honeypot protection for this form.
    honeypot_add_form_protection($form, $form_state, ['honeypot', 'time_restriction']);
    // Label is required so we will auto-fill on save.
    unset($form['label']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\rng_contact\Entity\RngContactInterface $contact */
    $contact = $form_state->get('contact');
    $display = EntityFormDisplay::collectRenderDisplay($contact, 'default');
    $display->extractFormValues($contact, $form, $form_state);
    // Auto-filling label.
    $contact->label = "{$contact->first_name->value} {$contact->last_name->value}";
    $contact->save();

    /** @var \Drupal\rng\RegistrationInterface $registration */
    $registration = $this->getEntity();
    $registrant = $this->registrantFactory->createRegistrant([
      'event' => $registration->getEvent(),
    ]);
    $registrant->setIdentity($contact);
    $registrant->setRegistration($registration);
    $registrant->save();

    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      $form_state->setRedirect('entity.node.real_registration.confirmation',
        ['node' => $node->id()]
      );
    }
  }

}
