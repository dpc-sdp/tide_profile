<?php

namespace Drupal\tide_event_atdw\Plugin\migrate\process;

use Drupal\Component\Utility\UrlHelper;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Validate a URL.
 *
 * The url_validate process plugin validates a URL string. It returns the URL
 * if valid, or FALSE otherwise.
 *
 * If the URL does not have a scheme, the process plugin will assume HTTP.
 *
 * Examples:
 *
 * @code
 * process:
 *   'destination_field_link/uri':
 *    -
 *      plugin: url_validate
 *      source: source_url
 *    -
 *      plugin: skip_on_empty
 *      method: process
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "url_validate"
 * )
 */
class UrlValidate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $scheme = parse_url($value, PHP_URL_SCHEME);
    if (!$scheme) {
      $value = 'http://' . ltrim($value, '/');
    }

    return UrlHelper::isValid($value, TRUE) ? $value : FALSE;
  }

}
