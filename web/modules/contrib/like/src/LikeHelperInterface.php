<?php

namespace Drupal\like;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for the Like helper service.
 */
interface LikeHelperInterface {

  /**
   * Callback to +1 the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity liked.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   User that likes the entity, default to current.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function like(EntityInterface $entity, AccountInterface $user = NULL);

  /**
   * Undoes a like.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity liked.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   User that likes the entity, default to current.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function unlike(EntityInterface $entity, AccountInterface $user = NULL);

  /**
   * Checks whether a user has liked an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity liked.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   User that likes the entity, default to current.
   *
   * @return bool
   *   TRUE if the user has liked the entity before, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function userHasLiked(EntityInterface $entity, AccountInterface $user = NULL):bool;

  /**
   * Get num of likes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity liked.
   *
   * @return int
   *   Number of likes.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNumOfLikes(EntityInterface $entity):int;

}
