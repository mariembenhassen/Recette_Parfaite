<?php

namespace Drupal\Tests\like\Functional;

/**
 * Tests the Like admin functions.
 *
 * @group Like
 */
class LikeAdminTest extends LikeBaseTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the admin screen.
   */
  public function testSettings() {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/config/user-interface/like');
    $assert_session->statusCodeEquals('200');
    $assert_session->pageTextNotContains('The configuration options have been saved.');
    $assert_session->fieldExists('enabled_entity_types[node]');
    $assert_session->checkboxNotChecked('enabled_entity_types[node]');
    $assert_session->fieldNotExists('enabled_entity_types[like]');
    $enabled_entity_types = \Drupal::configFactory()->get('like.settings')->get('enabled_entity_types');
    $this->assertFalse(isset($enabled_entity_types['node']));
    $assert_session->fieldExists('like_cookie_expiry_time');
    $assert_session->fieldExists('cache_type');
    $assert_session->fieldValueEquals('like_cookie_expiry_time', 31536000);
    $assert_session->fieldValueEquals('cache_type', 'entity');

    $edit = [
      'enabled_entity_types[node]' => 'node',
      'like_cookie_expiry_time' => '-1',
      'cache_type' => 'time',
    ];
    $this->submitForm($edit, $this->t('Save configuration'));
    $assert_session->checkboxChecked('enabled_entity_types[node]');
    $assert_session->pageTextContains('The configuration options have been saved.');

    $this->drupalGet('admin/config/user-interface/like');
    $assert_session->checkboxChecked('enabled_entity_types[node]');

    $enabled_entity_types = \Drupal::configFactory()->get('like.settings')->get('enabled_entity_types');
    $this->assertTrue($enabled_entity_types['node'] == 'node');

    $assert_session->fieldValueEquals('like_cookie_expiry_time', -1);
    $assert_session->fieldValueEquals('cache_type', 'time');
    $this->assertTrue(\Drupal::configFactory()->get('like.settings')->get('like_cookie_expiry_time') === -1);
    $this->assertTrue(\Drupal::configFactory()->get('like.settings')->get('cache_type') === 'time');
  }

}
