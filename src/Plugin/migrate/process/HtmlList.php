<?php

namespace Drupal\tide_event_atdw\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * This plugin generates an HTML List from an array.
 *
 * @MigrateProcessPlugin(
 *   id = "html_list",
 *   handle_multiples = TRUE
 * )
 *
 * Required configuration keys:
 *   - source: the array.
 *   - heading: (optional) the heading of the list.
 *   - heading_tag: (optional) the tag for the heading, default to h2.
 *   - type: (optional) the list type, can be either ul or ol, default to ul.
 *
 * Example usage:
 *
 * @code
 * 'source_list' => [
 *   'Item 1',
 *   'Item 2',
 *   'Item 3',
 * ]
 *
 * process:
 *   field_description:
 *     plugin: html_list
 *     source: source_list
 *     heading: 'My list'
 *     heading_tag: h2
 *     type: ul
 *
 * Result:
 * <h2>My list</h2><ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>
 * @endcode
 */
class HtmlList extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $return = '';
    $count = 0;
    $heading = $this->configuration['heading'] ?? '';
    if ($heading) {
      $heading_tag = !empty($this->configuration['heading_tag']) ? strtolower($this->configuration['heading_tag']) : 'div';
      $return = '<' . $heading_tag . '>' . $heading . '</' . $heading_tag . '>';
    }

    $type = !empty($this->configuration['type']) ? strtolower($this->configuration['type']) : 'ul';
    if ($type != 'ul' && $type != 'ol') {
      throw new MigrateException(sprintf('List type %s is not supported.', ['%s' => $type]));
    }

    if (is_array($value) || $value instanceof \Traversable) {
      $return .= '<' . $type . '>';
      foreach ($value as $item) {
        if (is_scalar($item) || (is_object($item)) && method_exists($item, '__toString')) {
          $return .= '<li>' . ((string) $item) . '</li>';
          $count++;
        }
      }
      $return .= '<' . $type . '>';
    }

    return $count ? $return : '';
  }

}
