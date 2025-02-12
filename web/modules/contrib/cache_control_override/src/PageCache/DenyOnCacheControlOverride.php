<?php

declare(strict_types=1);

namespace Drupal\cache_control_override\PageCache;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache policy for responses that have a bubbled max-age=0.
 *
 * @internal
 *   There is no extensibility promise for this class. To override this
 *   functionality, the service may be decorated. Or you may
 *   remove, or replace this class entirely in service registration by
 *   implementing a ServiceProvider.
 */
final class DenyOnCacheControlOverride implements ResponsePolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Response $response, Request $request): ?string {
    if (!$response instanceof CacheableResponseInterface) {
      return NULL;
    }

    if ($response->getCacheableMetadata()->getCacheMaxAge() === 0) {
      // @todo This will affect users using Internal Page Cache as well, find a way to document that.
      return static::DENY;
    }

    return NULL;
  }

}
