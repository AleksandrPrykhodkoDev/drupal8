<?php

namespace Drupal\ss_location\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class LocationTourForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ss_location_tour_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $location = NULL) {
    $form['#attached']['library'][] = 'ss_location/tour_form';
    $form['#attached']['drupalSettings']['ss_location']['tour_form']['schedules_dates'] = NULL;
    $form['#attached']['drupalSettings']['ss_location']['tour_form']['schedules_date_hours'] = NULL;

    $services_options = [];
    if ($location->getServiceKDV() == 1) {
      $services_options['KDV'] = t('@service', ['@service' => $location->getMainServiceKDVTitle()]);
    }

    if ($location->getServicePSZ() == 1) {
      $services_options['PSZ'] = t('@service', ['@service' => $location->getMainServicePSZTitle()]);
    }

    if ($location->getServiceBSO() == 1) {
      $services_options['BSO'] = t('@service', ['@service' => $location->getMainServiceBSOTitle()]);
    }

    $location_services_count = count($services_options);

    $form['Location'] = [
      '#type' => 'hidden',
      '#value' => $location->getPath()
    ];

    $form['LocationName'] = [
      '#type' => 'hidden',
      '#value' => $location->getName()
    ];

    $form['LocationId'] = [
      '#type' => 'hidden',
      '#value' => $location->getId()
    ];

    $form['ContactReason'] = [
      '#type' => 'hidden',
      '#value' => 'Tour'
    ];

    $campaign = NULL;
    if (!empty($_GET['Campaign'])) {
      $campaign = $_GET['Campaign'];
    }
    elseif (!empty($_COOKIE['Campaign'])) {
      $campaign = $_COOKIE['Campaign'];
    }

    $form['CampaignId'] = [
      '#type' => 'hidden',
      '#value' => $campaign
    ];

    $language = \Drupal::languageManager()->getCurrentLanguage()->getName();

    $form['Language'] = [
      '#type' => 'hidden',
      '#value' => $language
    ];

    $form['Source'] = [
      '#type' => 'hidden',
      '#value' => 1
    ];

    $form['TourDate'] = [
      '#type' => 'hidden',
    ];

    $form['TourTime'] = [
      '#type' => 'hidden',
    ];

    $form['TourId'] = [
      '#type' => 'hidden',
    ];

    $form_state->setCached(FALSE);

    if ($location_services_count > 1) {
      $form['services_wrapper']['subtitle'] = [
        '#type' => 'markup',
        '#markup' => t('Voor welke opvangvorm(en) wil je komen kijken?')
      ];

      $form['services_wrapper']['LocationServices'] = [
        '#title' => NULL,
        '#type' => 'checkboxes',
        '#options' => $services_options,
      ];
    }

    if ($location_services_count == 1) {
      $form['services_wrapper']['LocationServices'] = [
        '#type' => 'value',
        '#value' => [key($services_options)]
      ];
    }

    $form['address_wrapper']['NameTitle'] = [
      '#type' => 'radios',
      '#options' => [
        'mevrouw' => t('Mevr.'),
        'heer' => t('Dhr. *')
      ],
      '#required' => TRUE
    ];

    $form['address_wrapper']['NameFirst'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => t('Voornaam *'),
      ]
    ];

    $form['address_wrapper']['NameLast'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => t('Achternaam *'),
      ]
    ];

    $form['address_wrapper']['ContactEmail'] = [
      '#type' => 'email',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => t('E-mail *'),
        'data-rule-required' => 'true'
      ]
    ];

    $form['address_wrapper']['TitleContactPhone'] = [
      '#type' => 'markup',
      '#markup' => t('Als je ook je telefoonnummer invult, bellen of mailen we je voor een afspraak. Vul je alleen een e-mailadres in, dan mailen we.')
    ];

    $form['address_wrapper']['ContactPhone'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => t('Telefoonnummer'),
        'pattern' => ".{8,12}"
      ]
    ];

    $form['address_wrapper']['more'] = [
      '#type' => 'markup',
      '#markup' => t('Heb je wensen, vragen of is er iets anders dat je alvast wilt laten weten?'),
    ];

    $form['address_wrapper']['Remarks'] = [
      '#type' => 'textarea',
      '#attributes' => [
        'placeholder' => t('Type hier je tekst')
      ]
    ];

    $form['address_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Verstuur aanvraag')
    ];

    $form['text_wrapper']['HeaderText'] = [
      '#type' => 'markup',
      '#markup' => t('Wat gebeurt er als ik op \'verstuur aanvraag\' klik?')
    ];

    $form['text_wrapper']['BodyText'] = [
      '#type' => 'markup',
      '#markup' => t('Je ontvangt automatisch een bevestigingsmail. Onze locatiemanager neemt binnen 2 werkdagen contact met je op. De precieze datum voor de rondleiding spreken we dan met je af.')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $tour_id = $form_state->getValue('TourId');
    if ($tour_id) {
      $tour_schedules = ss_location_tour_schedules($form_state->getValue('LocationId'));

      $tour_valid = FALSE;
      foreach ($tour_schedules as $tour_schedule) {
        if (isset($tour_schedule[$tour_id])) {
          $tour_valid = TRUE;
        }
      }

      if ($tour_valid == FALSE) {
        $form_state->setErrorByName('tour_schedules', t('Application error. Tour cannot be booked.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::state();
    $crm_url = $config->get('services.crm');

    $campaign = $form_state->getValue('CampaignId');

    $services = $form_state->getValue('LocationServices');
    $LocationServices = [];
    foreach ($services as $service) {
      if ($service) {
        $LocationServices[] = $service;
      }
    }

    $collected_data = [
      'LocationId' => $form_state->getValue('LocationId'),
      'ContactReason' => $form_state->getValue('ContactReason'),
      'CampaignId' => $campaign,
      'Language' => $form_state->getValue('Language'),
      'Source' => $form_state->getValue('Source'),
      'TourDate' => $form_state->getValue('TourDate') . ' ' . $form_state->getValue('TourTime'),
      'TourId' => $form_state->getValue('TourId'),
      'LocationServices' => $LocationServices,
      'NameTitle' => $form_state->getValue('NameTitle'),
      'NameFirst' => $form_state->getValue('NameFirst'),
      'NameLast' => $form_state->getValue('NameLast'),
      'ContactPhone' => $form_state->getValue('ContactPhone'),
      'ContactEmail' => $form_state->getValue('ContactEmail'),
      'Remarks' => $form_state->getValue('Remarks')
    ];

    \Drupal::logger('location tour data')->notice('<pre>' . print_r($collected_data, 1) . '</pre>');
    $lead_id = strip_tags(ss_location_remote_request($crm_url, $collected_data));
    \Drupal::logger('location tour lead id')->notice('<pre>' . print_r($lead_id, 1) . '</pre>');

    $_SESSION['location_tour'] = [];
    if ($lead_id && stripos($lead_id, 'Error') === FALSE) {
      //Type=Tour&Service=[KDV|BSO|PSZ]&Campaign=[CampaignId]
      $query = [
        'Type' => 'Tour',
        'Service' => $collected_data['LocationServices'],
        'Campaign' => $campaign
      ];

      $_SESSION['location_tour']['location'] = $form_state->getValue('Location');
      $_SESSION['location_tour']['location_name'] = $form_state->getValue('LocationName');
      $_SESSION['location_tour']['time'] = time();
      $_SESSION['location_tour']['thank_you_page'] = 0;

      if ($collected_data['TourId']) {
        ss_location_tour_book($collected_data['TourId'], $lead_id);
      }

      if (in_array('KDV', $LocationServices) && $config->get("thank_you_page.tour_kdv")) {
        $_SESSION['location_tour']['thank_you_page'] = $config->get("thank_you_page.tour_kdv");
        $form_state->setRedirect('entity.node.canonical', ['node' => $config->get("thank_you_page.tour_kdv")], ['query' => $query]);
      }
      elseif (in_array('BSO', $LocationServices) && $config->get("thank_you_page.tour_bso")) {
        $_SESSION['location_tour']['thank_you_page'] = $config->get("thank_you_page.tour_bso");
        $form_state->setRedirect('entity.node.canonical', ['node' => $config->get("thank_you_page.tour_bso")], ['query' => $query]);
      }
      elseif (in_array('PSZ', $LocationServices) && $config->get("thank_you_page.tour_psz")) {
        $_SESSION['location_tour']['thank_you_page'] = $config->get("thank_you_page.tour_psz");
        $form_state->setRedirect('entity.node.canonical', ['node' => $config->get("thank_you_page.tour_psz")], ['query' => $query]);
      }
      else {
        $form_state->setRedirect('entity.ss_location.tour.thank', ['ss_location' => $form_state->getValue('Location')], ['query' => $query]);
      }
    }
    else {
      drupal_set_message(t('Application error. Response: ') . $lead_id, 'error');
    }
  }
}
