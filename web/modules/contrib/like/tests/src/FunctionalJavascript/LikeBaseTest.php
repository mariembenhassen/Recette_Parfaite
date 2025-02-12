<?php

namespace Drupal\Tests\like\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the Like basic functions.
 *
 * @group Like
 */
abstract class LikeBaseTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'user',
    'like',
    'like_test',
    'field',
    'node',
  ];

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authenticatedUser;

  /**
   * The node to test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->storage = $entity_type_manager->getStorage('like');
    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->authenticatedUser = $this->drupalCreateUser(['access content']);
    $this->node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Example page']);
    $config = $this->container->get('config.factory')->getEditable('like.settings');
    $config->set('enabled_entity_types', ['node'])->save();

    drupal_flush_all_caches();
  }

  /**
   * Gets the permissions for the admin user.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getAdministratorPermissions() {
    return [
      'access administration pages',
      'administer like configuration',
    ];
  }

}
