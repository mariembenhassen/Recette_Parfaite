<?php

declare(strict_types=1);

namespace Drupal\cache_control_override_test\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Test caching with a max age provided from the URL.
 */
final class CacheControl {

  /**
   * Content for testing.
   */
  const RESPONSE = 'Max age test content';

  /**
   * Test content with a specified max age.
   *
   * @param string|null $max_age
   *   Max-age value to be used in the response, or NULL to not a set max-age.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function __invoke($max_age = NULL): array|Response {
    $build = ['#plain_text' => static::RESPONSE];
    if (isset($max_age)) {
      $build['#cache']['max-age'] = $max_age;
    }
    return $build;
  }

}
