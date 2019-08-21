<?php

namespace Drupal\tide_event_atdw\Plugin\migrate_plus\data_parser;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\SimpleXml;

/**
 * Obtain XML data for migration using the SimpleXML API.
 *
 * @DataParser(
 *   id = "tide_simple_xml",
 *   title = @Translation("Tide Simple XML")
 * )
 */
class TideSimpleXml extends SimpleXml {

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl($url) {
    if (UrlHelper::isValid($url, TRUE)) {
      try {
        return parent::openSourceUrl($url);
      }
      catch (MigrateException $exception) {
        watchdog_exception('tide_event_atdw', $exception, NULL, [], RfcLogLevel::INFO);
      }
    }
    return FALSE;
  }

}
