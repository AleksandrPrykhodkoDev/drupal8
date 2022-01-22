<?php

namespace Drupal\nunavut_core;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * Class WeatherGcCa.
 *
 * Gets the weather data from https://weather.gc.ca/
 *
 * @package Drupal\nunavut_core
 */
class WeatherGcCa {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  const XML_BASE_ADDRESS = 'https://weather.gc.ca/rss/city/';

  const JS_BASE_ADDRESS = 'https://weather.gc.ca/wxlink/site_js/';

  const TIME_DATE_BASE_ADDRESS = 'https://www.timeanddate.com/astronomy/canada/';

  const INFORMER_BASE_ADDRESS = 'https://weather.gc.ca/wxlink/wxlink.html?lang=e&cityCode=';

  public const CITY_CODES = [
    'nu-22' => [
      'title' => 'Alert',
      'js_code' => 's0000821',
    ],
    'nu-10' => [
      'title' => 'Arctic Bay',
      'js_code' => 's0000412',
    ],
    'nu-20' => [
      'title' => 'Arviat',
      'js_code' => 's0000511',
    ],
    'nu-14' => [
      'title' => 'Baker Lake',
      'js_code' => 's0000487',
    ],
    'nu-15' => [
      'title' => 'Cambridge Bay',
      'js_code' => 's0000495',
    ],
    'nu-17' => [
      'title' => 'Chesterfield',
      'js_code' => 's0000501',
    ],
    'nu-18' => [
      'title' => 'Clyde River',
      'js_code' => 's0000504',
    ],
    'nu-9' => [
      'title' => 'Coral Harbour',
      'js_code' => 's0000798',
    ],
    'nu-19' => [
      'title' => 'Ennadai',
      'js_code' => 's0000206',
    ],
    'nu-11' => [
      'title' => 'Eureka',
      'js_code' => 's0000140',
    ],
    'nu-24' => [
      'title' => 'Gjoa Haven',
      'js_code' => 's0000820',
    ],
    'nu-12' => [
      'title' => 'Grise Fiord',
      'js_code' => 's0000177',
    ],
    'nu-23' => [
      'title' => 'Igloolik',
      'js_code' => 's0000540',
    ],
    'nu-21' => [
      'title' => 'Iqaluit',
      'js_code' => 's0000394',
    ],
    'nu-26' => [
      'title' => 'Kimmirut',
      'js_code' => 's0000587',
    ],
    'nu-2' => [
      'title' => 'Kinngait',
      'js_code' => 's0000694',
    ],
    'nu-13' => [
      'title' => 'Kugaaruk',
      'js_code' => 's0000587',
    ],
    'nu-16' => [
      'title' => 'Kugluktuk',
      'js_code' => 's0000498',
    ],
    'nu-1' => [
      'title' => 'Nanisivik',
      'js_code' => 's0000693',
    ],
    'nu-3' => [
      'title' => 'Naujaat',
      'js_code' => 's0000713',
    ],
    'nu-7' => [
      'title' => 'Pangnirtung',
      'js_code' => 's0000750',
    ],
    'nu-25' => [
      'title' => 'Pond Inlet',
      'js_code' => 's0000564',
    ],
    'nu-5' => [
      'title' => 'Qikiqtarjuaq',
      'js_code' => 's0000716',
    ],
    'nu-28' => [
      'title' => 'Rankin Inlet',
      'js_code' => 's0000678',
    ],
    'nu-27' => [
      'title' => 'Resolute',
      'js_code' => 's0000672',
    ],
    'nu-29' => [
      'title' => 'Sanikiluaq',
      'js_code' => 's0000689',
    ],
    'nu-4' => [
      'title' => 'Sanirajak',
      'js_code' => 's0000714',
    ],
    'nu-8' => [
      'title' => 'Taloyoak',
      'js_code' => 's0000774',
    ],
    'nu-6' => [
      'title' => 'Whale Cove',
      'js_code' => 's0000749',
    ],
  ];

  public const ICON_CODES = [
    '00' => 'wi-day-sunny',
    '01' => 'wi-day-sunny-overcast',
    '02' => 'wi-day-sunny-overcast',
    '03' => 'wi-day-sunny-overcast',
    '04' => 'wi-day-sunny-overcast',
    '05' => 'wi-day-sunny-overcast',
    '06' => 'wi-day-rain',
    '07' => 'wi-day-snow',
    '08' => 'wi-day-snow',
    '09' => 'wi-day-thunderstorm',
    '10' => 'wi-cloudy',
    '11' => 'wi-rain',
    '12' => 'wi-rain',
    '13' => 'wi-rain',
    '14' => 'wi-rain',
    '15' => 'wi-snow',
    '16' => 'wi-snow',
    '17' => 'wi-snow',
    '18' => 'wi-snow',
    '19' => 'wi-thunderstorm',
    '20' => 'wi-fog',
    '21' => 'wi-fog',
    '22' => 'wi-day-sunny-overcast',
    '23' => 'wi-fog',
    '24' => 'wi-fog',
    '25' => 'wi-sandstorm',
    '26' => 'wi-sandstorm',
    '27' => 'wi-hail',
    '28' => 'wi-rain',
    '29' => 'wi-na',
    '30' => 'wi-night-clear',
    '31' => 'wi-night-alt-cloudy',
    '32' => 'wi-night-alt-cloudy',
    '33' => 'wi-night-alt-cloudy',
    '34' => 'wi-night-alt-cloudy',
    '35' => 'wi-night-alt-cloudy',
    '36' => 'wi-night-alt-rain',
    '37' => 'wi-night-alt-showers',
    '38' => 'wi-night-alt-snow',
    '39' => 'wi-night-thunderstorm',
    '40' => 'wi-snow-wind',
    '41' => 'wi-tornado',
    '42' => 'wi-tornado',
  ];

  public const CITIES = [
    'Alert' => [
      'code' => 'nu-22',
      'js_code' => 's0000821',
    ],
    'Arctic Bay' => [
      'code' => 'nu-10',
      'js_code' => 's0000412',
    ],
    'Arviat' => [
      'code' => 'nu-20',
      'js_code' => 's0000511',
    ],
    'Baker Lake' => [
      'code' => 'nu-14',
      'js_code' => 's0000487',
    ],
    'Cambridge Bay' => [
      'code' => 'nu-15',
      'js_code' => 's0000495',
    ],
    'Chesterfield' => [
      'code' => 'nu-17',
      'js_code' => 's0000501',
    ],
    'Chesterfield Inlet' => [
      'code' => 'nu-17',
      'js_code' => 's0000501',
    ],
    'Clyde River' => [
      'code' => 'nu-18',
      'js_code' => 's0000504',
    ],
    'Coral Harbour' => [
      'code' => 'nu-9',
      'js_code' => 's0000798',
    ],
    'Ennadai' => [
      'code' => 'nu-19',
      'js_code' => 's0000206',
    ],
    'Eureka' => [
      'code' => 'nu-11',
      'js_code' => 's0000140',
    ],
    'Gjoa Haven' => [
      'code' => 'nu-24',
      'js_code' => 's0000820',
    ],
    'Grise Fiord' => [
      'code' => 'nu-12',
      'js_code' => 's0000177',
    ],
    'Igloolik' => [
      'code' => 'nu-23',
      'js_code' => 's0000540',
    ],
    'Iqaluit' => [
      'code' => 'nu-21',
      'js_code' => 's0000394',
    ],
    'Kimmirut' => [
      'code' => 'nu-26',
      'js_code' => 's0000587',
    ],
    'Kinngait' => [
      'code' => 'nu-2',
      'js_code' => 's0000694',
    ],
    'Kugaaruk' => [
      'code' => 'nu-13',
      'js_code' => 's0000587',
    ],
    'Kugluktuk' => [
      'code' => 'nu-16',
      'js_code' => 's0000498',
    ],
    'Nanisivik' => [
      'code' => 'nu-1',
      'js_code' => 's0000693',
    ],
    'Naujaat' => [
      'code' => 'nu-3',
      'js_code' => 's0000713',
    ],
    'Repulse Bay' => [
      'code' => 'nu-3',
      'js_code' => 's0000713',
    ],
    'Pangnirtung' => [
      'code' => 'nu-7',
      'js_code' => 's0000750',
    ],
    'Pond Inlet' => [
      'code' => 'nu-25',
      'js_code' => 's0000564',
    ],
    'Qikiqtarjuaq' => [
      'code' => 'nu-5',
      'js_code' => 's0000716',
    ],
    'Rankin Inlet' => [
      'code' => 'nu-28',
      'js_code' => 's0000678',
    ],
    'Resolute' => [
      'code' => 'nu-27',
      'js_code' => 's0000672',
    ],
    'Sanikiluaq' => [
      'code' => 'nu-29',
      'js_code' => 's0000689',
    ],
    'Sanirajak' => [
      'code' => 'nu-4',
      'js_code' => 's0000714',
    ],
    'Hall Beach' => [
      'code' => 'nu-4',
      'js_code' => 's0000714',
    ],
    'Taloyoak' => [
      'code' => 'nu-8',
      'js_code' => 's0000774',
    ],
    'Whale Cove' => [
      'code' => 'nu-6',
      'js_code' => 's0000749',
    ],
  ];

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected TitleResolverInterface $titleResolver;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected PathMatcherInterface $pathMatcher;

  /**
   * The xml encoder.
   *
   * @var \Symfony\Component\Serializer\Encoder\XmlEncoder
   */
  protected XmlEncoder $xmlEncoder;

  /**
   * The Logger Interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The Inflector factory.
   *
   * @var \Doctrine\Inflector\Inflector
   */
  protected Inflector $inflector;

  /**
   * Constructs a WeatherGcCa object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection,
    CacheBackendInterface $cache,
    Token $token,
    TitleResolverInterface $title_resolver,
    RequestStack $request_stack,
    QueueFactory $queue_factory,
    PathMatcherInterface $path_matcher
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->cache = $cache;
    $this->token = $token;
    $this->titleResolver = $title_resolver;
    $this->requestStack = $request_stack;
    $this->queueFactory = $queue_factory;
    $this->pathMatcher = $path_matcher;
    $this->xmlEncoder = new XmlEncoder();
    $this->logger = $this->getLogger('nunavut_core');

    $this->inflector = InflectorFactory::createForLanguage(Language::ENGLISH)
      ->build();
  }

  /**
   * Format address string for xml file with data.
   *
   * @param string $code
   *   City code ([code]).
   *
   * @return string
   *   Formatted URI string.
   */
  private function getXmlAddress(string $code): string {
    return sprintf(
      '%s%s_e.xml',
      $this::XML_BASE_ADDRESS,
      $code
    );
  }

  /**
   * Format address string for js file with data.
   *
   * @param string $code
   *   City code ([js_code]).
   *
   * @return string
   *   Formatted URI string.
   */
  private function getJsAddress(string $code): string {
    return sprintf(
      '%s%s_e.js',
      $this::JS_BASE_ADDRESS,
      $code
    );
  }

  /**
   * Gets the code for city.
   *
   * @param string $city
   *   City name.
   *
   * @return string[]|null
   *   City codes.
   */
  private function getCityCode(string $city): ?array {
    return $this::CITIES[$city] ?? NULL;
  }

  /**
   * Gets weather for city.
   *
   * @param string $city
   *   City name.
   *
   * @return array|null
   *   Xml and js for parsing.
   */
  public function getCityWeather(string $city): ?array {
    if ($code = $this->getCityCode($city)) {
      $result = [];

      $xml_address = $this->getXmlAddress($code['code']);
      $js_address = $this->getJsAddress($code['js_code']);

      try {
        $request = $this
          ->httpClient
          ->request('GET', $xml_address);

        if ($request->getStatusCode() != 200) {
          $result['xml'] = NULL;
        }

        $result['xml'] = $this
          ->xmlEncoder
          ->decode(
            $request->getBody()->getContents(),
            'xml'
          );
      }
      catch (GuzzleException $e) {
        $this->logger->error($e->getMessage());
      }

      try {
        $request = $this
          ->httpClient
          ->request('GET', $js_address);

        if ($request->getStatusCode() != 200) {
          $result['js'] = NULL;
        }

        $result['js'] = $request->getBody()->getContents();
      }
      catch (GuzzleException $e) {
        $this->logger->error($e->getMessage());
      }

      $this->updateAstronomy($city, $result);

      return $this->parseWeatherStrings($result);
    }

    return NULL;
  }

  /**
   * Gets the weather conditions from xml and js.
   *
   * @param array $weather
   *   Parsed xml and js.
   *
   * @return array
   *   Weather conditions.
   */
  private function parseWeatherStrings(array $weather): array {
    $current_conditions = $this->getWeatherConditionsXml($weather['xml']['entry']);
    $current_conditions['gc_image_code'] = $this->getIconCode($weather['js']);
    $current_conditions += $weather['time_astronomy'];

    return $current_conditions;
  }

  /**
   * Gets the weather conditions.
   *
   * @param array $entry
   *   Parsed xml entry.
   *
   * @return array
   *   The weather conditions.
   */
  private function getWeatherConditionsXml(array $entry): array {
    $condition_matches = [
      'text' => '/<b>Condition:<\/b>(.*)<br\/>/',
      'temperature' => '/<b>Temperature:<\/b>(.*)&deg;C <br\/>/',
      'temperature_unit' => '/&deg;(.*)<br\/>/',
      'pressure' => '/<b>Pressure \/ Tendency:<\/b>(.*)<br\/>/',
      'visibility' => '/<b>Visibility:<\/b>(.*)<br\/>/',
      'humidity' => '/<b>Humidity:<\/b>(.*)<br\/>/',
      'wind_chill' => '/<b>Wind Chill:<\/b>(.*)<br\/>/',
      'wind' => '/<b>Wind:<\/b>(.*)<br\/>/',
    ];

    $result = [];

    foreach ($entry as $record) {
      if (strpos($record['title'], 'Current Conditions') !== FALSE) {
        foreach ($condition_matches as $key => $value) {
          $output_array = [];
          if (preg_match($value, $record['summary']['#'], $output_array)) {
            $result[$key] = trim($output_array[1]);
          }
        }
      }
    }

    return $result;
  }

  /**
   * Get weather icon code from js.
   *
   * @param string $js
   *   Js string.
   *
   * @return string|null
   *   Icon code or null.
   */
  private function getIconCode(string $js): ?string {
    if (preg_match('/var obIconCode = "(.*)";/', $js, $output_array)) {
      return self::ICON_CODES[$output_array[1]] ?? 'wi-na';
    }

    return NULL;
  }

  /**
   * Get address for city on https://www.timeanddate.com/astronomy/ service.
   *
   * @param string $city
   *   City name.
   *
   * @return string
   *   Address string.
   */
  private function getAstronomyAddress(string $city): string {
    if ($this->inflector->urlize($city) == 'kimmirut') {
      $city = '@5992095';

      return sprintf(
        '%s%s',
        'https://www.timeanddate.com/astronomy/',
        $city
      );
    }

    return sprintf(
      '%s%s',
      $this::TIME_DATE_BASE_ADDRESS,
      $this->inflector->urlize($city)
    );
  }

  /**
   * Gets city astronomy properties(sunrise, sunset, etc).
   *
   * @param string $city
   *   City name.
   * @param array $result
   *   Result.
   */
  public function updateAstronomy(string $city, array &$result) {
    $time_astronomy_address = $this->getAstronomyAddress($city);

    $matches = [
      'sunset' => '/<th>Sunset Today:(.*)<td>(.*)<span/U',
      'sunrise' => '/<th>Sunrise Today:(.*)<td>(.*)<span/U',
      'moonset' => '/<th>Moonset Today:(.*)<td>(.*)<span/U',
      'moonrise' => '/<th>Moonrise Today:(.*)<td>(.*)<span/U',
      'daylight-hours' => '/<th>Daylight Hours(.*)">(.*)<\/td>/U',
    ];

    try {
      $request = $this
        ->httpClient
        ->request('GET', $time_astronomy_address);

      if ($request->getStatusCode() != 200) {
        $result['time_astronomy'] = NULL;
      }
      else {
        $content = $request->getBody()->getContents();

        foreach ($matches as $key => $value) {
          $output_array = [];
          if (preg_match($value, $content, $output_array)) {
            $result['time_astronomy'][$key] = $output_array[2];

            if ($key != 'daylight-hours') {
              $date = new \DateTime('now');
              $formatted = sprintf(
                '%s %s',
                $date->format('Y-m-d'),
                $result['time_astronomy'][$key]
              );

              if ($date = \DateTime::createFromFormat('Y-m-d H:i', $formatted)) {
                $result['time_astronomy'][$key] = $date->format('g:i A');
              }
            }
          }
        }
      }
    }
    catch (GuzzleException $e) {
      $this->logger->error($e->getMessage());
      $result['time_astronomy'] = [];
    }
  }

}
