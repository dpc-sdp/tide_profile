<?php

namespace Drupal\tide_event_atdw\Plugin\migrate\source;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;

/**
 * Source plugin for retrieving events from ATDW.
 *
 * @MigrateSource(
 *   id = "atdw_event"
 * )
 */
class AtdwEvent extends Url {

  /**
   * The API Endpoint of ATDW.
   */
  const API_ENDPOINT = 'https://atlas.atdw-online.com.au';

  /**
   * The API Output format.
   */
  const API_OUTPUT_FORMAT = 'xml';

  /**
   * The API pagination size.
   */
  const API_PAGER_SIZE = 100;

  /**
   * Post-import action: Keep.
   */
  const POST_IMPORT_ACTION_KEEP = 'keep';

  /**
   * Post-import action: Archive.
   */
  const POST_IMPORT_ACTION_ARCHIVE = 'archive';

  /**
   * Post-import action: Rollback.
   */
  const POST_IMPORT_ACTION_ROLLBACK = 'rollback';

  /**
   * Default cache lifetime to 6h.
   */
  const CACHE_LIFETIME = 21600;

  /**
   * ATDW API Key.
   *
   * @var string
   */
  protected $apiKey = 'ATDW_API_KEY';

  /**
   * ATDW Search API State filter.
   *
   * @var string
   */
  protected $state = 'VIC';

  /**
   * ATDW Search API Category filter.
   *
   * @var string
   */
  protected $category = 'EVENT';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Http fetcher.
   *
   * @var \Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http
   */
  protected $httpFetcher;

  /**
   * The Migrate last imported.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $lastImportedStorage;

  /**
   * Returns the currently active global container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface|null
   *   The container.
   *
   * @throws \Drupal\Core\DependencyInjection\ContainerNotInitializedException
   */
  protected static function getContainer() {
    return \Drupal::getContainer();
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $this->migration = $migration;
    $this->lastImportedStorage = static::getContainer()->get('keyvalue')->get('migrate_last_imported');
    $this->configFactory = static::getContainer()->get('config.factory');
    $settings = $this->configFactory->get('tide_event_atdw.settings');
    if ($settings) {
      $this->apiKey = $settings->get('api_key');
      $default_site = (string) $settings->get('default_site');
      $configuration['constants']['site'] = !empty($configuration['constants']['site']) ? $configuration['constants']['site'] : $default_site;
    }

    /** @var \Drupal\migrate_plus\DataFetcherPluginManager $fetcher_manager */
    $fetcher_manager = static::getContainer()->get('plugin.manager.migrate_plus.data_fetcher');
    $this->httpFetcher = $fetcher_manager->createInstance($configuration['data_fetcher_plugin'], $configuration);
    if (!($this->httpFetcher instanceof Http)) {
      throw new MigrateException('The data_fetcher_plugin of the source plugin must be an instance of Http or HttpCache.');
    }

    $this->state = $settings->get('state');
    $this->category = $settings->get('category');

    if (empty($configuration['cache_lifetime'])) {
      $configuration['cache_lifetime'] = static::CACHE_LIFETIME;
    }
    $this->configuration['cache_lifetime'] = $configuration['cache_lifetime'];

    $configuration['urls'] = $this->getEventUrls();

    $migration->setTrackLastImported(TRUE);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    $this->prepareUpdateUrls();
  }

  /**
   * Prepare the URL for events marked as STATUS_NEEDS_UPDATE.
   */
  protected function prepareUpdateUrls() {
    /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $mapper */
    $mapper = $this->migration->getIdMap();
    $update_count = $mapper->updateCount();
    if ($update_count) {
      $ids = array_keys($this->getIds());
      $product_id_index = array_search('product_id', $ids);
      if ($product_id_index !== FALSE) {
        $need_updates = $mapper->getRowsNeedingUpdate($update_count);
        foreach ($need_updates as $row) {
          $product_id = $row->{('sourceid' . ($product_id_index + 1))} ?? '';
          if ($product_id) {
            $this->sourceUrls[] = static::buildApiUrl('product', $this->apiKey, ['productId' => $product_id]);
          }
        }
      }

      $this->sourceUrls = array_unique($this->sourceUrls);
      $this->configuration['urls'] = $this->sourceUrls;
    }
  }

  /**
   * Returns ATDW Product API URLs for Events.
   *
   * @return array
   *   The urls.
   *
   * @see http://developer.atdw.com.au/ATDWO-search.html
   * @see http://developer.atdw.com.au/ATDWO-delta.html
   * @see http://developer.atdw.com.au/ATDWO-getproduct.html
   */
  protected function getEventUrls() {
    $cid = 'tide_event_atdw:list:' . $this->category . ':' . $this->state;
    $cached_urls = $this->getCache()->get($cid);
    if (!empty($cached_urls)) {
      return $cached_urls->data;
    }

    // Cache miss.
    $product_ids = [];
    $product_urls = [];
    $inactive_product_ids = [];
    $total_results = NULL;
    $total_pages = 1;
    $current_page = 1;

    $query = [
      'cats' => $this->category,
      'st' => $this->state,
      'size' => static::API_PAGER_SIZE,
      'fl' => implode(',', [
        'product_id',
        'status',
      ]),
    ];

    // Attempt to use delta update.
    $last_imported = $this->lastImportedStorage->get($this->migration->id(), 0);
    if ($last_imported) {
      $query['delta'] = date('Y-m-d H:i:s', $last_imported / 1000);
      $cid .= ':delta:' . date('Ymd-His', $last_imported / 1000);
    }

    while ($current_page <= $total_pages) {
      $query['pge'] = $current_page++;
      $search_url = static::buildApiUrl('products', $this->apiKey, $query);
      try {
        $response = $this->httpFetcher->getResponseContent($search_url);
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_event_atdw', $exception, NULL, [], RfcLogLevel::INFO);
        continue;
      }

      if (empty($response)) {
        continue;
      }

      try {
        $xml = new \SimpleXMLElement($response);

        if ($total_results === NULL) {
          $total_results = (int) $xml->number_of_results;
          if ($total_results) {
            $total_pages = (int) ceil($total_results / static::API_PAGER_SIZE);
          }
        }

        if ($total_results) {
          foreach ($xml->products->product_record as $product) {
            $product_id = (string) $product->product_id;
            if ($product->status == 'ACTIVE') {
              $product_url = static::buildApiUrl('product', $this->apiKey, ['productId' => $product_id]);
              $product_ids[$product_id] = $product_id;
              $product_urls[$product_id] = $product_url;
            }
            else {
              $inactive_product_ids[$product_id] = $product_id;
            }
          }
        }
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_event_atdw', $exception, NULL, [], RfcLogLevel::INFO);
      }
    }

    // Track the Product IDs.
    if ($last_imported) {
      $this->updateTrackedProductIds($product_ids, $inactive_product_ids);
    }
    elseif (!empty($product_ids)) {
      $this->trackProductIds($product_ids);
    }

    $product_urls = array_values($product_urls);
    if (count($product_urls)) {
      // Cache the urls for 6 hours.
      $expire = $this->configuration['cache_lifetime'];
      try {
        $this->getCache()
          ->set($cid, $product_urls, ($expire >= 0) ? (REQUEST_TIME + $expire) : Cache::PERMANENT);
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_event_atdw', $exception, NULL, [], RfcLogLevel::INFO);
      }
    }

    return $product_urls;
  }

  /**
   * Track the Product IDs.
   *
   * All migrated products missing from these tracked IDs will be removed in
   * POST_IMPORT event.
   *
   * @param array $product_ids
   *   List of product IDs.
   *
   * @throws \Exception
   */
  protected function trackProductIds(array $product_ids) {
    /** @var \Drupal\Core\Database\Connection $database */
    $database = static::getContainer()->get('database');
    $transaction = $database->startTransaction('tide_event_atdw_tracking');

    $database->truncate('migrate_tracking_tide_event_atdw')->execute();

    $chunks = array_chunk($product_ids, 100);
    foreach ($chunks as $chunk) {
      $insert = $database->insert('migrate_tracking_tide_event_atdw')
        ->fields(['product_id']);
      foreach ($chunk as $product_id) {
        $insert->values([
          'product_id' => $product_id,
        ]);
      }
      $insert->execute();
    }
  }

  /**
   * Delta-update the tracked the Product IDs.
   *
   * All migrated products missing from these tracked IDs will be removed in
   * POST_IMPORT event.
   *
   * @param array $product_ids
   *   List of product IDs to be tracked.
   * @param array $inactive_product_ids
   *   List of product IDs to be removed.
   *
   * @throws \Exception
   */
  protected function updateTrackedProductIds(array $product_ids, array $inactive_product_ids) {
    /** @var \Drupal\Core\Database\Connection $database */
    $database = static::getContainer()->get('database');
    $transaction = $database->startTransaction('tide_event_atdw_tracking');

    // Update Product IDs.
    foreach ($product_ids as $product_id) {
      $database->merge('migrate_tracking_tide_event_atdw')
        ->key('product_id', $product_id)
        ->fields(['product_id' => $product_id])
        ->execute();
    }

    // Remove inactive Product IDs.
    $chunks = array_chunk($inactive_product_ids, 100);
    foreach ($chunks as $chunk) {
      $database->delete('migrate_tracking_tide_event_atdw')
        ->condition('product_id', $chunk, 'IN')
        ->execute();
    }
  }

  /**
   * Build an ATDW API URL.
   *
   * @param string $api
   *   The API name, eg. products, product, productservice, mlp,...
   * @param string $key
   *   The API key.
   * @param array $query
   *   The query.
   *
   * @return string
   *   The API URL.
   *
   * @see http://developer.atdw.com.au
   */
  public static function buildApiUrl($api, $key, array $query) {
    $apiUrl = static::API_ENDPOINT . '/api/atlas/' . $api;
    unset($query['key'], $query['out']);
    $query['key'] = $key;
    $query['out'] = self::API_OUTPUT_FORMAT;

    return $apiUrl . '?' . UrlHelper::buildQuery($query);
  }

}
