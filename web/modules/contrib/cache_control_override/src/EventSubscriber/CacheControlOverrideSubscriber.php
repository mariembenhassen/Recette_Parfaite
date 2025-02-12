<?php

declare(strict_types=1);

namespace Drupal\cache_control_override\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cache Control Override.
 *
 * @internal
 *   There is no extensibility promise for this class. To override this
 *   functionality, you may subscribe to events at a higher priority, then
 *   set $event->stopPropagation(). The service may be decorated. Or you may
 *   remove, or replace this class entirely in service registration by
 *   implementing a ServiceProvider.
 */
final class CacheControlOverrideSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new CacheControlOverrideSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Overrides cache control header if any of override methods are enabled.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onRespond(ResponseEvent $event): void {
    if (FALSE === $event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();

    // If the current response isn't an implementation of the
    // CacheableResponseInterface, then there is nothing we can override.
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    // If FinishResponseSubscriber didn't set the response as cacheable, then
    // don't override anything.
    if (FALSE === $response->headers->hasCacheControlDirective('max-age')) {
      return;
    }

    if (FALSE === $response->headers->hasCacheControlDirective('public')) {
      return;
    }

    $maxAge = (int) $response->getCacheableMetadata()->getCacheMaxAge();

    // We treat permanent cache max-age as default therefore we don't override
    // the max-age.
    if ($maxAge !== CacheBackendInterface::CACHE_PERMANENT) {
      // If max-age is not uncacheable (0), check if max-age should be changed.
      if ($maxAge > 0) {
        // Force minimum max-age if configured.
        $minimum = $this->getMaxAgeMinimum();
        if ($minimum !== NULL) {
          $maxAge = max($minimum, $maxAge);
        }

        // Force maximum max-age if configured.
        $maximum = $this->getMaxAgeMaximum();
        if ($maximum !== NULL && $maximum !== CacheBackendInterface::CACHE_PERMANENT) {
          $maxAge = min($maximum, $maxAge);
        }
      }
      $response->headers->set('Cache-Control', 'public, max-age=' . $maxAge);
    }
  }

  /**
   * Get the minimum max-age.
   *
   * @return int|null
   *   The minimum max-age, or null if no minimum.
   */
  protected function getMaxAgeMinimum(): ?int {
    $minimum = $this->configFactory->get('cache_control_override.settings')->get('max_age.minimum');
    return $minimum !== NULL ? (int) $minimum : NULL;
  }

  /**
   * Get the maximum max-age.
   *
   * @return int|null
   *   The maximum max-age, or null if no maximum.
   */
  protected function getMaxAgeMaximum(): ?int {
    $maximum = $this->configFactory->get('cache_control_override.settings')->get('max_age.maximum');
    return $maximum !== NULL ? (int) $maximum : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => [
        ['onRespond'],
      ],
    ];
  }

}
