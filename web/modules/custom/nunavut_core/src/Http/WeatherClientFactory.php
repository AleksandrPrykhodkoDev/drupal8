<?php

namespace Drupal\nunavut_core\Http;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

/**
 * Defines a client factory for Yahoo Weather.
 */
class WeatherClientFactory extends ClientFactory {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new ClientFactory instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\HandlerStack $stack
   *   The handler stack.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    HandlerStack $stack
  ) {
    parent::__construct($stack);

    $this->stack = $stack;
    $this->configFactory = $config_factory;
  }

  /**
   * Constructs a new Yahoo Weather client object.
   *
   * @param array $config
   *   The config for the client.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client.
   */
  public function fromOptions(array $config = []) {
    $settings = $this
      ->configFactory
      ->get('nunavut_core.settings')
      ->get('weather_settings');

    if (
      !empty($settings['app_id'])
      && !empty($settings['consumer_key'])
      && !empty($settings['consumer_secret'])
    ) {
      $middleware = new Oauth1([
        'consumer_key'    => $settings['consumer_key'],
        'consumer_secret' => $settings['consumer_secret'],
        'token_secret' => '',
      ]);

      $this->stack->push($middleware);

      $config = [
        RequestOptions::AUTH => 'oauth',
        RequestOptions::HEADERS => [
          'X-Yahoo-App-Id' => $settings['app_id'],
        ],
        'base_uri' => 'https://weather-ydn-yql.media.yahoo.com/',
        'handler' => $this->stack,
      ];
    }

    return parent::fromOptions($config);
  }

}
