<?php

namespace Drupal\like;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\like\Entity\LikeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Like helper class.
 */
class LikeHelper implements LikeHelperInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an LikeHelper object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, TimeInterface $time, ConfigFactoryInterface $config_factory) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->time = $time;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function like(EntityInterface $entity, AccountInterface $user = NULL) {
    if (empty($user)) {
      $user = $this->currentUser;
    }

    $settings = $this->configFactory->get('like.settings');
    $storage = $this->entityTypeManager->getStorage('like');
    $like = $storage->create([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'uid' => $user->id(),
      'value' => 1,
    ]);
    $like->save();

    if ($user->isAnonymous()) {
      $entity_type_id = $entity->getEntityTypeId();
      $cookie = $this->requestStack->getCurrentRequest()->cookies->get('Drupal_visitor_like');
      $cookie_data = $cookie ? Json::decode($cookie) : [];
      if (empty($cookie_data) || empty($cookie_data[$entity_type_id])) {
        $cookie_data[$entity_type_id] = [$entity->id()];
      }
      else {
        $cookie_data[$entity_type_id][] = $entity->id();
        $cookie_data[$entity_type_id] = array_unique($cookie_data[$entity_type_id]);
      }
      $cookie_data = Json::encode($cookie_data);
      $like_cookie_expiry_time = $settings->get('like_cookie_expiry_time');
      $expire = $this->time->getRequestTime() + $like_cookie_expiry_time;
      if (isset($this->requestStack->getCurrentRequest()->cookies)) {
        $this->requestStack->getCurrentRequest()->cookies->set('Drupal_visitor_like', $cookie_data);
      }
      setrawcookie('Drupal.visitor.like', rawurlencode($cookie_data), $expire, '/');
    }

    // On like/unlike, we clear the cache of the entity liked so the results are
    // fresh if the user has selected an entity based cache mode.
    if ($settings->get('cache_type') == 'entity') {
      Cache::invalidateTags($entity->getCacheTags());
      $this->entityTypeManager->getStorage($entity->getEntityTypeId())->resetCache([$entity->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unlike(EntityInterface $entity, AccountInterface $user = NULL) {
    if (empty($user)) {
      $user = $this->currentUser;
    }

    $settings = $this->configFactory->get('like.settings');
    $storage = $this->entityTypeManager->getStorage('like');
    $likes = $storage->loadByProperties([
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'uid' => $this->currentUser->id(),
    ]);
    // In case of authenticated users, we delete all the records as it is
    // supposed to be one or none per user and entity.
    if ($user->isAuthenticated()) {
      foreach ($likes as $like) {
        $like->delete();
      }
    }
    else {
      // For anonymous users, we delete one of the likes as it is not relevant
      // which one.
      $like = reset($likes);
      if ($like instanceof LikeInterface) {
        $like->delete();
      }

      // And also reset the cookie.
      $cookie = $this->requestStack->getCurrentRequest()->cookies->get('Drupal_visitor_like');
      $cookie_data = $cookie ? Json::decode($cookie) : [];
      $entity_type_id = $entity->getEntityTypeId();
      if (in_array($entity->id(), $cookie_data[$entity_type_id])) {
        $cookie_data[$entity_type_id] = array_filter($cookie_data[$entity_type_id], fn($e) => !in_array($e, [$entity->id()]));
        $cookie_data = Json::encode($cookie_data);
        $like_cookie_expiry_time = $settings->get('like_cookie_expiry_time');
        $expire = $this->time->getRequestTime() + $like_cookie_expiry_time;
        if (isset($this->requestStack->getCurrentRequest()->cookies)) {
          $this->requestStack->getCurrentRequest()->cookies->set('Drupal_visitor_like', $cookie_data);
        }
        setrawcookie('Drupal.visitor.like', rawurlencode($cookie_data), $expire, '/');
      }
    }

    // On like/unlike, we clear the cache of the entity liked so the results are
    // fresh if the user has selected an entity based cache mode.
    if ($settings->get('cache_type') == 'entity') {
      Cache::invalidateTags($entity->getCacheTags());
      $this->entityTypeManager->getStorage($entity->getEntityTypeId())->resetCache([$entity->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function userHasLiked(EntityInterface $entity, AccountInterface $user = NULL):bool {
    if (empty($user)) {
      $user = $this->currentUser;
    }

    if ($user->isAuthenticated()) {
      $query = $this->entityTypeManager->getStorage('like')->getQuery();
      $likes = (int) $query->accessCheck(FALSE)
        ->condition('entity_type', $entity->getEntityTypeId())
        ->condition('entity_id', $entity->id())
        ->condition('uid', $this->currentUser->id())
        ->count()
        ->execute();
      return $likes > 0;
    }
    else {
      $cookie = $this->requestStack->getCurrentRequest()->cookies->get('Drupal_visitor_like');
      $cookie_data = $cookie ? Json::decode($cookie) : [];
      $entity_type_id = $entity->getEntityTypeId();
      if (empty($cookie_data) || empty($cookie_data[$entity_type_id])) {
        return FALSE;
      }
      return in_array($entity->id(), $cookie_data[$entity_type_id]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNumOfLikes(EntityInterface $entity):int {
    $query = $this->entityTypeManager->getStorage('like')->getQuery();
    return (int) $query->accessCheck(FALSE)
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityTypeId())
      ->count()
      ->execute();
  }

}
