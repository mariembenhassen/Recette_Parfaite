<?php

namespace Drupal\like;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the like schema handler.
 */
class LikeStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    if ($data_table = $this->storage->getDataTable()) {
      $schema[$data_table]['indexes'] += [
        'like__entity_type__entity_id' => ['entity_type', 'entity_id'],
      ];
    }

    return $schema;
  }

}
