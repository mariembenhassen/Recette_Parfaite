<?php

namespace Drupal\like\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Like entities.
 *
 * @ingroup like
 */
interface LikeInterface extends ContentEntityInterface, EntityOwnerInterface {}
