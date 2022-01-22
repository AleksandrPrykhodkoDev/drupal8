<?php

namespace Drupal\nunavut_core\Plugin\Field\FieldFormatter;

use CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface;
use Drupal\address\AddressInterface;
use Drupal\address\Plugin\Field\FieldFormatter\AddressPlainFormatter;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\nunavut_core\WeatherGcCa;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\RequestOptions;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Language\LanguageInterface;

/**
 * Plugin implementation of the 'Yahoo Weather' formatter.
 *
 * @FieldFormatter(
 *   id = "yahoo_weather_address",
 *   module = "nunavut_core",
 *   label = @Translation("Yahoo Weather"),
 *   field_types = {
 *     "address"
 *   }
 * )
 */
class YahooWeatherAddressFormatter extends AddressPlainFormatter implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;
  use MessengerTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * WeatherGcCa service.
   *
   * @var \Drupal\nunavut_core\WeatherGcCa
   */
  protected WeatherGcCa $weatherGcCa;

  /**
   * Constructs an AddressPlainFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface $address_format_repository
   *   The address format repository.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   * @param \CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface $subdivision_repository
   *   The subdivision repository.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   The client.
   * @param \Drupal\nunavut_core\WeatherGcCa $alternate_weather
   *   The alternate weather service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    AddressFormatRepositoryInterface $address_format_repository,
    CountryRepositoryInterface $country_repository,
    SubdivisionRepositoryInterface $subdivision_repository,
    ConfigFactoryInterface $config_factory,
    ClientInterface $client,
    WeatherGcCa $alternate_weather
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
      $address_format_repository,
      $country_repository,
      $subdivision_repository
    );

    $this->addressFormatRepository = $address_format_repository;
    $this->countryRepository = $country_repository;
    $this->subdivisionRepository = $subdivision_repository;
    $this->configFactory = $config_factory;
    $this->client = $client;
    $this->weatherGcCa = $alternate_weather;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('address.address_format_repository'),
      $container->get('address.country_repository'),
      $container->get('address.subdivision_repository'),
      $container->get('config.factory'),
      $container->get('nunavut_core.http_client_weather'),
      $container->get('nunavut_core.weather.gc.ca')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewElement($item, $langcode);
    }

    return $elements;
  }

  /**
   * Builds a renderable array for a single Yahoo Weather address item.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array.
   */
  protected function viewElement(AddressInterface $address, $langcode) {
    $country_code = $address->getCountryCode();
    $address_format = $this->addressFormatRepository->get($country_code);
    $values = $this->getValues($address, $address_format);
    $settings = $this->getSettings();

    $config = $this
      ->configFactory
      ->get('nunavut_core.settings')
      ->get('weather_settings');

    if (
      empty($config['app_id'])
      || empty($config['consumer_key'])
      || empty($config['consumer_secret'])
    ) {
      $this->messenger()->addWarning(
        $this->t('Please configuration the weather widget.')
      );

      return [];
    }

    $request = $this->client->get('forecastrss', [
      RequestOptions::QUERY => [
        'format' => 'json',
        'location' => $values['locality'] . ',' . $country_code,

      ],
    ]);

    $data = Json::decode($request->getBody());

    if (!empty($data['location']['city'])) {
      $weather['location'] = implode(', ', [
        trim(Html::escape($data['location']['city'])),
        trim(Html::escape($data['location']['region'])),
        trim(Html::escape($data['location']['country'])),
      ]);

      $temp = isset($data['current_observation']['condition']['temperature'])
        ? Html::escape($data['current_observation']['condition']['temperature'])
        : '';

      $weather['temperature'] = $settings['unit'] == 'C' && is_numeric($temp)
        ? round(($temp - 32) * 5 / 9)
        : $temp;

      $weather['temperature_unit'] = $settings['unit'];

      $weather['text'] =
        isset($data['current_observation']['condition']['text'])
          ? Html::escape($data['current_observation']['condition']['text'])
          : '';

      if ($settings['image']) {
        $weather['image_code'] =
          isset($data['current_observation']['condition']['code'])
            ? Html::escape($data['current_observation']['condition']['code'])
            : '';
      }

      if ($settings['humidity']) {
        $weather['humidity'] =
          isset($data['current_observation']['atmosphere']['humidity'])
            ? Html::escape($data['current_observation']['atmosphere']['humidity'])
            : '';
      }

      if ($settings['visibility']) {
        $weather['visibility'] =
          isset($data['current_observation']['atmosphere']['visibility'])
            ? Html::escape($data['current_observation']['atmosphere']['visibility'])
            : '';
      }

      if ($settings['sunrise']) {
        $weather['sunrise'] =
          isset($data['current_observation']['astronomy']['sunrise'])
            ? Html::escape($data['current_observation']['astronomy']['sunrise'])
            : '';
      }

      if ($settings['sunset']) {
        $weather['sunset'] =
          isset($data['current_observation']['astronomy']['sunset'])
            ? Html::escape($data['current_observation']['astronomy']['sunset'])
            : '';
      }

      if (empty($data['current_observation'])) {
        $weather = $this->weatherGcCa->getCityWeather($data['location']['city']);
      }

      return [
        '#theme' => 'yahoo_weather_field_formatter',
        '#weather' => $weather,
        '#attached' => [
          'library' => [
            'nunavut_core/weather.icons',
          ],
        ],
        '#cache' => [
          'max-age' => $settings['cache'],
          'contexts' => [
            'languages:' . LanguageInterface::TYPE_INTERFACE,
          ],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::getDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();

    $elements['unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Unit'),
      '#options' => [
        'F' => $this->t('Fahrenheit'),
        'C' => $this->t('Celsius'),
      ],
      '#default_value' => $settings['unit'] ?? 'F',
      '#description' => $this->t('Select Fahrenheit or Celsius for temperature unit.'),
    ];

    foreach ($this->getWeatherOptions() as $label) {
      $elements[$label] = [
        '#type' => 'select',
        '#title' => $this->t('@label', [
          '@label' => ucfirst($label),
        ]),
        '#options' => [
          FALSE => $this->t('No'),
          TRUE => $this->t('Yes'),
        ],
        '#default_value' => $settings[$label] ?? FALSE,
        '#description' => $this->t('Select Yes to show @state', [
          '@state' => $label,
        ]),
      ];
    }

    $elements['cache'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache'),
      '#options' => $this->getCacheOptions(),
      '#default_value' => $settings['cache'] ?? 0,
      '#description' => $this->t('Time for cache the block.'),
    ];

    return $elements + parent::settingsForm($form, $form_state);
  }

  /**
   * Get the Default Settings.
   *
   * @return array
   *   The default settings.
   */
  public static function getDefaultSettings() {
    return [
      'unit' => 'C',
      'image' => TRUE,
      'humidity' => TRUE,
      'visibility' => TRUE,
      'sunrise' => TRUE,
      'sunset' => TRUE,
      'cache' => 21600,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $summary = [];

    $summary[] = $this->t('Temperature unit: @state', [
      '@state' => $settings['unit'] == 'F' ? $this->t('Fahrenheit') : $this->t('Celsius'),
    ]);

    foreach ($this->getWeatherOptions() as $label) {
      $summary[] = [
        '#markup' => $this->t('Show @label: @state', [
          '@label' => $label,
          '@state' => $settings[$label] ? $this->t('Yes') : $this->t('No'),
        ]),
      ];
    }

    $cache = $this->getCacheOptions();

    $summary[] = $this->t('Cache: @state', [
      '@state' => $cache[$settings['cache']],
    ]);

    return $summary;
  }

  /**
   * Get weather options array.
   *
   * @return string[]
   *   Return weather options.
   */
  private function getWeatherOptions(): array {
    return [
      'image',
      'humidity',
      'visibility',
      'sunrise',
      'sunset',
    ];
  }

  /**
   * Get cache options.
   *
   * @return array
   *   Return cache time options.
   */
  private function getCacheOptions(): array {
    return [
      0 => $this->t('No Cache'),
      1800 => $this->t('30 min'),
      3600 => $this->t('1 hour'),
      21600 => $this->t('6 hours'),
      86400 => $this->t('One day'),
    ];
  }

}
