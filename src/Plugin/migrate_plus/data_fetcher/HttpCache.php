<?php

namespace Drupal\tide_event_atdw\Plugin\migrate_plus\data_fetcher;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;
use Drupal\tide_event_atdw\Plugin\migrate\source\AtdwEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the HTTP data fetcher with cache.
 *
 * All options of the default HTTP fetcher are available.
 * New option:
 *  - cache_lifetime: in seconds, 0 for no cache, default to 21600.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: url
 *   data_fetcher_plugin: http_cache
 *   cache_lifetime: 21600
 * @endcode
 *
 * @DataFetcher(
 *   id = "http_cache",
 *   title = @Translation("HTTP with Cache")
 * )
 */
class HttpCache extends Http {

  /**
   * Cache life time.
   *
   * @var int
   */
  protected $cacheLifetime;

  /**
   * Migrate cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * True if gzip functions are available.
   *
   * @var bool
   */
  protected $gzipAvailable;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cache = $cache;
    $this->cacheLifetime = $configuration['cache_lifetime'] ?? AtdwEvent::CACHE_LIFETIME;
    $this->gzipAvailable = function_exists('gzcompress') && function_exists('gzuncompress');
    if (!empty($configuration['disable_gzip_cache'])) {
      $this->gzipAvailable = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.migrate')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseContent($url) {
    if (!UrlHelper::isValid($url, TRUE)) {
      return NULL;
    }

    $cid = 'tide_event_atdw:http_cache:url:' . hash('sha256', $url);
    if ($this->gzipAvailable) {
      $cid .= ':gz';
    }
    $cached_response = $this->cache->get($cid);
    if (!empty($cached_response)) {
      return $this->gzipAvailable ? gzuncompress($cached_response->data) : $cached_response->data;
    }

    $response = parent::getResponseContent($url);

    $content = $response->getContents();
    if ($content) {
      try {
        $this->cache->set($cid, $this->gzipAvailable ? gzcompress($content) : $content, ($this->cacheLifetime >= 0) ? (REQUEST_TIME + $this->cacheLifetime) : Cache::PERMANENT);
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_event_atdw', $exception, NULL, [], RfcLogLevel::INFO);
      }
    }

    return $response;
  }

}
