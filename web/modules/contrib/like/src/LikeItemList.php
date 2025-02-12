<?php

namespace Drupal\like;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes the likes for a given entity.
 */
class LikeItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * Computes the likes for a given entity.
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $delta = 0;
    $this->list[$delta] = $this->createItem($delta, \Drupal::service('like.helper')->getNumOfLikes($entity));
  }

}
