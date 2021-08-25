<?php

/**
 * @file
 * Post update functions for ASF Common.
 */

/**
 * Create Sendinblue sign up form.
 */
function asf_common_post_update_create_sendinblue_signup_form() {
  \Drupal::entityTypeManager()->getStorage('sendinblue_signup_form')->create([
    'name' => 'newsletter_subscribe',
    'mode' => 1,
    'title' => 'Subscribe to the newsletter',
    'status' => 1,
    'settings' => [
      'description' => [
        'value' => 'Newsletter',
        'format' => 'basic_html',
      ],
      'fields' => [
        'mergefields' => [
          'EMAIL' => [
            'check' => 1,
            'label' => 'Email',
            'required' => 1,
          ],
          'NOM' => [
            'check' => 1,
            'label' => 'Nom',
            'required' => 1,
          ],
          'PRENOM' => [
            'check' => 1,
            'label' => 'Prénom',
            'required' => 1,
          ],
          'SMS' => [
            'check' => 0,
            'label' => 'SMS',
            'required' => 0,
          ],
          'CIVILITE' => [
            'check' => 0,
            'label' => 'CIVILITE',
            'required' => 0,
          ],
          'CODE_POSTAL' => [
            'check' => 0,
            'label' => 'CODE_POSTAL',
            'required' => 0,
          ],
          'COMMUNE' => [
            'check' => 0,
            'label' => 'COMMUNE',
            'required' => 0,
          ],
          'TELEPHONE' => [
            'check' => 0,
            'label' => 'TELEPHONE',
            'required' => 0,
          ],
          'SOCIETE' => [
            'check' => 0,
            'label' => 'SOCIETE',
            'required' => 0,
          ],
          'COMPAGNIE' => [
            'check' => 0,
            'label' => 'COMPAGNIE',
            'required' => 0,
          ],
          'ADRESSE' => [
            'check' => 0,
            'label' => 'ADRESSE',
            'required' => 0,
          ],
          'PAYS' => [
            'check' => 0,
            'label' => 'PAYS',
            'required' => 0,
          ],
          'COMMUNAUTES_AVIATION_SANS_FRONTIERES' => [
            'check' => 0,
            'label' => 'COMMUNAUTES_AVIATION_SANS_FRONTIERES',
            'required' => 0,
          ],
          'PUBLICATIONS' => [
            'check' => 0,
            'label' => 'PUBLICATIONS',
            'required' => 0,
          ],
          'NOM_ET_PRENOM' => [
            'check' => 0,
            'label' => 'NOM_ET_PRENOM',
            'required' => 0,
          ],
          'PROFIL' => [
            'check' => 0,
            'label' => 'PROFIL',
            'required' => 0,
          ],
          'GROUPE' => [
            'check' => 0,
            'label' => 'GROUPE',
            'required' => 0,
          ],
          'OPTIN_TIME' => [
            'check' => 0,
            'label' => 'OPTIN_TIME',
            'required' => 0,
          ],
          'CONFIRM_TIME' => [
            'check' => 0,
            'label' => 'CONFIRM_TIME',
            'required' => 0,
          ],
          'CONFIRM_IP' => [
            'check' => 0,
            'label' => 'CONFIRM_IP',
            'required' => 0,
          ],
          'TAGS' => [
            'check' => 0,
            'label' => 'TAGS',
            'required' => 0,
          ],
          'PROFIL_2' => [
            'check' => 0,
            'label' => 'PROFIL_2',
            'required' => 0,
          ],
          'MISSIONS' => [
            'check' => 0,
            'label' => 'MISSIONS',
            'required' => 0,
          ],
          'DOMAINE' => [
            'check' => 0,
            'label' => 'DOMAINE',
            'required' => 0,
          ],
          'DOMAINE_MEDIA' => [
            'check' => 0,
            'label' => 'DOMAINE_MEDIA',
            'required' => 0,
          ],
          'EMISSION' => [
            'check' => 0,
            'label' => 'EMISSION',
            'required' => 0,
          ],
          'TWITTER' => [
            'check' => 0,
            'label' => 'TWITTER',
            'required' => 0,
          ],
          'MEDIA' => [
            'check' => 0,
            'label' => 'MEDIA',
            'required' => 0,
          ],
          'FACEBOOK' => [
            'check' => 0,
            'label' => 'FACEBOOK',
            'required' => 0,
          ],
          'TYPE_DE_MEDIA' => [
            'check' => 0,
            'label' => 'TYPE_DE_MEDIA',
            'required' => 0,
          ],
          'PRODUCTION' => [
            'check' => 0,
            'label' => 'PRODUCTION',
            'required' => 0,
          ],
          'FONCTION' => [
            'check' => 0,
            'label' => 'FONCTION',
            'required' => 0,
          ],
          'DOMAINE_SECTEUR' => [
            'check' => 0,
            'label' => 'DOMAINE_SECTEUR',
            'required' => 0,
          ],
          'LISTE' => [
            'check' => 0,
            'label' => 'LISTE',
            'required' => 0,
          ],
          'PROFESSION' => [
            'check' => 0,
            'label' => 'PROFESSION',
            'required' => 0,
          ],
          'OPT_IN' => [
            'check' => 0,
            'label' => 'OPT_IN',
            'required' => 0,
          ],
          'AVIS' => [
            'check' => 0,
            'label' => 'AVIS',
            'required' => 0,
          ],
          'REGION' => [
            'check' => 0,
            'label' => 'REGION',
            'required' => 0,
          ],
          'LAST_CHANGED' => [
            'check' => 0,
            'label' => 'LAST_CHANGED',
            'required' => 0,
          ],
        ],
        'submit_button' => 'Subscribe',
      ],
      'subscription' => [
        'settings' => [
          'list' => '19',
          'redirect_url' => '',
          'email_confirmation' => 0,
          'template' => '12',
        ],
        'messages' => [
          'success' => 'Thank you, you have successfully registered!',
          'general' => 'Something wrong occured',
          'existing' => 'You have already registered',
          'invalid' => 'Your email address is invalid',
        ],
      ],
    ],
  ])->save();
}
