<?php

namespace Drupal\tide_event_atdw\Plugin\migrate\process;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate\process\EntityGenerate;

/**
 * This plugin extends the entity_generate with support of revisions.
 *
 * @MigrateProcessPlugin(
 *   id = "entity_revision_generate"
 * )
 *
 * @see \Drupal\migrate_plus\Plugin\migrate\process\EntityGenerate
 *
 * All the configuration from the entity_generate plugin applies here.
 *
 * Example usage with values and default_values configuration:
 * @code
 * destination:
 *   plugin: 'entity:node'
 * process:
 *   type:
 *     plugin: default_value
 *     default_value: page
 *   foo: bar
 *   field_tags:
 *     plugin: entity_generate
 *     source: tags
 *     default_values:
 *       description: Default description
 *     values:
 *       field_long_description: some_source_field
 *       field_foo: '@foo'
 * @endcode
 */
class EntityRevisionGenerate extends EntityGenerate {

  /**
   * Generates an entity for a given value.
   *
   * @param string $value
   *   Value to use in creation of the entity.
   *
   * @return array|null
   *   An array of entity id and revision of of the generated entity.
   */
  protected function generateEntity($value) {
    if (!empty($value)) {
      $entity = $this->entityManager
        ->getStorage($this->lookupEntityType)
        ->create($this->entity($value));
      $entity->save();

      if ($entity instanceof RevisionableInterface) {
        return [$entity->id(), $entity->getRevisionId()];
      }

      throw new MigrateException(sprintf('Entity type %s does not support revisions.', $entity->getEntityTypeId()));
    }

    return NULL;
  }

}
