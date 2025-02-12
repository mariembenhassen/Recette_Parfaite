<?php

declare(strict_types=1);

namespace Drupal\Tests\cache_control_override\Functional;

use Drupal\cache_control_override_test\Controller\CacheControl;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the cache control override.
 *
 * @group cache_control_override
 */
final class CacheControlOverrideMaxAgeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The max age set by Drupal when page caching is enabled.
   */
  const DEFAULT_MAX_AGE = 1800;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cache_control_override',
    'cache_control_override_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->config('system.performance')
      ->set('cache.page.max_age', static::DEFAULT_MAX_AGE)
      ->save();
  }

  /**
   * Test the cache properties in response header data.
   */
  public function testMaxAge(): void {
    // Max age not set.
    $this->drupalGet(Url::fromRoute('cache_control_override_test.max_age'));
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=' . static::DEFAULT_MAX_AGE . ', public');

    // Permanent.
    $this->drupalGet(Url::fromRoute('cache_control_override_test.max_age', route_parameters: [
      'max_age' => '-1',
    ]));
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=' . static::DEFAULT_MAX_AGE . ', public');

    // Max age set.
    $this->drupalGet(Url::fromRoute('cache_control_override_test.max_age', route_parameters: [
      'max_age' => '333',
    ]));
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=333, public');

    // Uncacheable.
    $this->drupalGet(Url::fromRoute('cache_control_override_test.max_age', route_parameters: [
      'max_age' => '0',
    ]));
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=0, public');
  }

  /**
   * Test the max age is coerced to minimum age.
   */
  public function testMaxAgeMinimum(): void {
    $assertMinimum = 100;
    $this->config('cache_control_override.settings')
      ->set('max_age.minimum', $assertMinimum)
      ->save();

    // Max-age must not be changed if not over minimum.
    $this->drupalGet('cco/150');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=150, public');

    // Max-age must be changed if under minimum.
    $this->drupalGet('cco/50');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=' . $assertMinimum . ', public');

    // Permanent or uncacheable must not be coerced.
    $this->drupalGet('cco/-1');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=' . static::DEFAULT_MAX_AGE . ', public');
    $this->drupalGet('cco/0');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=0, public');
  }

  /**
   * Test the max age is coerced to maximum age.
   */
  public function testMaxAgeMaximum(): void {
    $assertMaximum = 100;
    $this->config('cache_control_override.settings')
      ->set('max_age.maximum', $assertMaximum)
      ->save();

    // Max-age must not be changed if not under maximum.
    $this->drupalGet('cco/50');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=50, public');

    // Max-age must be changed if over maximum.
    $this->drupalGet('cco/150');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=' . $assertMaximum . ', public');

    // Permanent or uncacheable must not be coerced.
    $this->drupalGet('cco/-1');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=' . static::DEFAULT_MAX_AGE . ', public');
    $this->drupalGet('cco/0');
    $this->assertSession()->pageTextContains(CacheControl::RESPONSE);
    $this->assertSession()->responseHeaderContains('Cache-Control', 'max-age=0, public');
  }

}
