<?php

namespace Drupal\tide_event_atdw\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * This plugin converts a SimpleXML object into an array.
 *
 * @MigrateProcessPlugin(
 *   id = "simplexml_to_array"
 * )
 *
 * Required configuration keys:
 * - source: the SimpleXML object.
 * - item_selector: (optional) the XPath selector.
 *
 * Example usage:
 *
 * @code
 * <?xml version="1.0"?>
 * <events>
 *   <event_dates>
 *     <row>
 *       <start_date>2019-06-27</start_date>
 *       <end_date>2019-06-27</end_date>
 *       <start_time>19:30</start_time>
 *       <end_time>22:10</end_time>
 *     </row>
 *     <row>
 *       <start_date>2019-06-28</start_date>
 *       <end_date>2019-06-28</end_date>
 *       <start_time>19:30</start_time>
 *       <end_time>22:10</end_time>
 *     </row>
 *     <row>
 *       <start_date>2019-06-29</start_date>
 *       <end_date>2019-06-29</end_date>
 *       <start_time>19:30</start_time>
 *       <end_time>22:10</end_time>
 *     </row>
 *   </event_dates>
 * </events>
 *
 * source:
 *   data_parser_plugin: simple_xml
 *   item_selector: /events
 * fields:
 *   -
 *     name: event_dates
 *     label: Dates
 *     selector: event_dates
 * process:
 *   field_event_description:
 *     -
 *       plugin: simplexml_to_array
 *       source: event_dates
 *       item_selector: row
 *     -
 *       plugin: sub_process
 *       process:
 *         event_date:
 *           plugin: format_date
 *           source: start_date
 *           from_format: 'Y-m-d'
 *           to_format: 'd M Y'
 *     -
 *       plugin: flatten
 *     -
 *       plugin: html_list
 *       heading: 'Event Dates'
 *
 * Result of simplexml_to_array:
 * [
 *    0 => [
 *      'start_date' => '2019-06-27',
 *      'end_date' => '2019-06-27',
 *      'start_time' => '19:30',
 *      'end_time' => '22:10',
 *    ],
 *    1 => [
 *      'start_date' => '2019-06-28',
 *      'end_date' => '2019-06-28',
 *      'start_time' => '19:30',
 *      'end_time' => '22:10',
 *    ],
 *    2 => [
 *      'start_date' => '2019-06-29',
 *      'end_date' => '2019-06-29',
 *      'start_time' => '19:30',
 *      'end_time' => '22:10',
 *    ],
 * ],
 *
 * Final result:
 * <h2>Event Dates</h2>
 * <ul>
 *   </li>27 Jun 2019</li>
 *   </li>28 Jun 2019</li>
 *   </li>29 Jun 2019</li>
 * </ul>
 * @endcode
 */
class SimpleXmlToArray extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value instanceof \SimpleXMLElement) {
      $selector = !empty($this->configuration['item_selector']) ? $this->configuration['item_selector'] : '';
      /** @var \SimpleXMLElement $value */
      if ($selector) {
        $selected_value = $value->xpath($selector);
        $json_values = json_encode($selected_value);
      }
      else {
        $json_values = json_encode($value);
      }
      $values = json_decode($json_values, TRUE);
      return $values;
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
