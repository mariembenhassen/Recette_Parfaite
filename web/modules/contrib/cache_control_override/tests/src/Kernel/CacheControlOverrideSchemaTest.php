<?php

declare(strict_types=1);

namespace Drupal\Tests\cache_control_override\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests that cache_control_override schema is correct.
 *
 * @group cache_control_override
 */
final class CacheControlOverrideSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['cache_control_override'];

  /**
   * Tests schema.
   */
  public function testSchema(): void {
    $this->assertConfigSchemaByName('cache_control_override.settings');
  }

}
