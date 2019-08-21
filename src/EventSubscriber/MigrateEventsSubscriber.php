<?php

namespace Drupal\tide_event_atdw\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\tide_event_atdw\Plugin\migrate\source\AtdwEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class MigrateEventsSubscriber.
 */
class MigrateEventsSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Migration manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationManager;

  /**
   * Tide Event ATDW migrations.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface[]
   */
  protected $migrations;

  /**
   * The current migration.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $currentMigration;

  /**
   * MigrateEventsSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_manager
   *   The migration manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, Connection $database, MigrationPluginManagerInterface $migration_manager) {
    $this->configFactory = $config_factory;
    $this->settings = $this->configFactory->get('tide_event_atdw.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->migrationManager = $migration_manager;
    $this->migrations = $this->migrationManager->createInstances([
      'tide_event_atdw',
      'tide_event_atdw_details',
      'tide_event_atdw_image',
      'tide_event_atdw_image_file',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_IMPORT => 'onPostImport',
      MigrateEvents::POST_ROW_SAVE => 'onPostRowSaveResetNodeStaticCache',
    ];
  }

  /**
   * React to MigrateEvents::POST_IMPORT event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The MigrateEvents::POST_IMPORT event.
   */
  public function onPostImport(MigrateImportEvent $event) {
    $this->currentMigration = $event->getMigration();
    $this->migrations[$this->currentMigration->id()] = $this->currentMigration;

    // Only reacts if source plugin is ATDW Event.
    $source = $this->currentMigration->getSourcePlugin();
    if ($source->getPluginId() != 'atdw_event') {
      return;
    }

    // No action when Post-import action is Keep, bail out early.
    $post_import_action = $this->settings->get('post_import');
    if ($post_import_action == AtdwEvent::POST_IMPORT_ACTION_KEEP) {
      $this->resetEntityInternalCache();
      return;
    }

    // Retrieves the migrated nodes not in the latest tracked source.
    $results = $this->getNonExistentSource();
    if (!empty($results)) {
      // Apply Post-Import action to each non-existent source.
      foreach ($results as $result) {
        switch ($post_import_action) {
          // Only archive Event nodes.
          case AtdwEvent::POST_IMPORT_ACTION_ARCHIVE:
            if ($this->currentMigration->id() == 'tide_event_atdw') {
              $this->archiveMigratedSource($result->product_id);
            }
            break;

          // Rollback.
          case AtdwEvent::POST_IMPORT_ACTION_ROLLBACK:
            $this->rollbackMigratedSource($result->product_id);
            break;

          // No action.
          case AtdwEvent::POST_IMPORT_ACTION_KEEP:
          default:
            break;
        }
      }
    }

    $this->resetEntityInternalCache();
  }

  /**
   * Get the list of migrated source ID no longer in the current source.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   The results.
   */
  protected function getNonExistentSource() {
    /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $map */
    $map = $this->currentMigration->getIdMap();
    $migrate_table = $map->mapTableName();
    $tracking_table = 'migrate_tracking_tide_event_atdw';

    // Retrieves the source key.
    $source_ids = array_keys($this->currentMigration->getSourcePlugin()->getIds());
    $product_id_index = array_search('product_id', $source_ids);
    if ($product_id_index === FALSE) {
      return NULL;
    }
    $source_key = 'sourceid' . ($product_id_index + 1);

    try {
      $query = $this->database->select($migrate_table, 'map');
      $query->addField('map', $source_key, 'product_id');
      $query->leftJoin($tracking_table, 'tracking', 'tracking.product_id = map.' . $source_key);
      $query->isNull('tracking.product_id');
      return $query->execute();
    }
    catch (\Exception $e) {
      watchdog_exception('tide_event_atdw', $e);
      return NULL;
    }
  }

  /**
   * Archive a migrated source.
   *
   * @param string $product_id
   *   The Source Product ID.
   */
  protected function archiveMigratedSource($product_id) {
    try {
      $map = $this->currentMigration->getIdMap();
      $destination_to_archive = $map->lookupDestinationIds(['product_id' => $product_id]);
      if (!empty($destination_to_archive)) {
        $destination_to_archive = reset($destination_to_archive);
        $nid = reset($destination_to_archive);
        if (empty($nid)) {
          return;
        }

        /** @var \Drupal\node\Entity\Node $node */
        $node = $this->entityTypeManager->getStorage('node')
          ->load($nid);
        if ($node && $node->bundle() == 'event') {
          $moderation_state = $node->get('moderation_state')->getString();
          if ($moderation_state != 'archived') {
            $node->setPublished(FALSE)
              ->set('moderation_state', 'archived')
              ->save();
          }
        }
      }
    }
    catch (\Exception $e) {
      watchdog_exception('tide_event_atdw', $e);
    }
  }

  /**
   * Rollback a migrated source.
   *
   * @param string $product_id
   *   The source Product ID.
   */
  protected function rollbackMigratedSource($product_id) {
    // Rollback the source from all Tide Event ATDW migrations.
    foreach ($this->migrations as $migration) {
      try {
        $destination_to_rollback = $migration->getIdMap()
          ->lookupDestinationIds(['product_id' => $product_id]);
        if (!empty($destination_to_rollback)) {
          $destination_to_rollback = reset($destination_to_rollback);
          $migration->getDestinationPlugin()
            ->rollback($destination_to_rollback);
          // Remove the source from Migrate Mapping.
          $migration->getIdMap()
            ->delete(['product_id' => $product_id]);
        }
      }
      catch (\Exception $e) {
        watchdog_exception('tide_event_atdw', $e);
      }
    }
  }

  /**
   * Reset entity memory cache to reclaim memory.
   */
  protected function resetEntityInternalCache() {
    $this->entityTypeManager->useCaches(FALSE);
    $this->entityTypeManager->useCaches(TRUE);
  }

  /**
   * React to MigrateEvents::POST_ROW_SAVE event.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The MigrateEvents::POST_ROW_SAVE event.
   */
  public function onPostRowSaveResetNodeStaticCache(MigratePostRowSaveEvent $event) {
    // Only reacts if source plugin is ATDW Event.
    $source = $event->getMigration()->getSourcePlugin();
    if ($source->getPluginId() != 'atdw_event') {
      return;
    }

    $destination_plugin = $event->getMigration()->getDestinationPlugin();
    if ($destination_plugin->getPluginId() == 'entity:node') {
      try {
        $this->entityTypeManager->getStorage('node')
          ->resetCache($event->getDestinationIdValues());
      }
      catch (\Exception $exception) {
        // Ignore.
      }
    }
  }

}
