<?php

namespace Drupal\tide_event_atdw\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * This plugin checks if a value is empty and conditionally run a pipeline.
 *
 * @MigrateProcessPlugin(
 *   id = "if_empty",
 *   handle_multiples = TRUE
 * )
 *
 * Required configuration keys:
 * - source: the value to evaluate.
 * - on_true: the plugin(s) that will process when the source is empty.
 * - on_false: the plugin(s) that will process when the source is not empty.
 *
 * Example usage:
 * @code
 * process:
 *   field_ticket_price:
 *     plugin: if_empty
 *     source: source_price
 *     on_true:
 *       plugin: default_value
 *       default_value: 'Free entry'
 *     on_false:
 *       -
 *         plugin: default_value
 *         default_value: '$'
 *       -
 *         plugin: concat
 *         source:
 *           -
 *           - source_price
 * @endcode
 */
class IfEmpty extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $new_row = clone $row;
    if (empty($value)) {
      $migrate_executable->processRow($new_row, [$destination_property => $this->configuration['on_true']]);
    }
    else {
      $migrate_executable->processRow($new_row, [$destination_property => $this->configuration['on_false']]);
    }
    $new_value = $new_row->getDestinationProperty($destination_property);

    return $new_value;
  }

}
