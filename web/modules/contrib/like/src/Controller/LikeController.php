<?php

namespace Drupal\like\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Like Controller.
 */
class LikeController extends ControllerBase {

  /**
   * Handle the request to get the number of likes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Target entity.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   Json response with the likes and labels.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException|\Exception
   */
  public function handler(Request $request, EntityInterface $entity) : CacheableJsonResponse {
    $response = new CacheableJsonResponse([
      'likes' => $entity->get('likes')->value ?? 0,
    ]);
    $settings = $this->config('like.settings');
    $cache_metadata = new CacheableMetadata();

    switch ($settings->get('cache_type')) {
      case 'time':
        $like_cookie_expiry_time = $settings->get('like_cookie_expiry_time');
        $cache_metadata->setCacheMaxAge($like_cookie_expiry_time);
        $response->setExpires(new \DateTime('+' . $like_cookie_expiry_time . ' seconds'));
        break;

      case 'entity':
      default:
        // We add the parent entity as cache dependency and clear it in the
        // like/unlike because the alternative would be to add each like entity
        // and that could grow exponentially.
        $cache_metadata->addCacheableDependency($entity);
    }

    $response->addCacheableDependency($cache_metadata);
    $response->addCacheableDependency($settings);

    return $response;
  }

}
