<?php

namespace Drupal\Tests\like\FunctionalJavascript;

use Drupal\Component\Serialization\Json;
use Drupal\like\Entity\Like;

/**
 * Tests the Like functions.
 *
 * @group Like
 */
class LikeTest extends LikeBaseTest {

  /**
   * Tests a like as anonymous user.
   */
  public function testLikeAnonymous() {
    $this->drupalLogout();
    $this->drupalGet('node/' . $this->node->id());
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session->pageTextContains('Like');
    $assert_session->pageTextContains('(0) Likes');

    $page->find('css', '.like-txt')->click();
    $assert_session->pageTextContains('You liked this');
    $assert_session->pageTextContains('(1) Likes');
  }

  /**
   * Tests a like as authenticated user.
   */
  public function testLikeAuthenticated() {
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet('node/' . $this->node->id());
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session->pageTextContains('Like');
    $assert_session->pageTextContains('(0) Likes');

    $page->find('css', '.like-txt')->click();
    $assert_session->pageTextContains('You liked this');
    $assert_session->pageTextContains('(1) Likes');
  }

  /**
   * Tests load liked entity as anonymous user without cookies.
   */
  public function testLoadLikeAnonymousNoCookie() {
    $like = Like::create([
      'entity_type' => $this->node->getEntityTypeId(),
      'entity_id' => $this->node->id(),
      'uid' => 0,
      'value' => 1,
    ]);
    $like->save();
    $this->drupalLogout();
    $this->drupalGet('node/' . $this->node->id());
    $assert_session = $this->assertSession();

    $assert_session->pageTextContains('Like');
    $assert_session->pageTextContains('(1) Likes');
  }

  /**
   * Tests unlike as anonymous user.
   */
  public function testUnLikeAnonymous() {
    $like = Like::create([
      'entity_type' => $this->node->getEntityTypeId(),
      'entity_id' => $this->node->id(),
      'uid' => 0,
      'value' => 1,
    ]);
    $like->save();
    $this->drupalLogout();
    $assert_session = $this->assertSession();
    $session = $this->getSession();

    $cookie_data = [];
    $cookie_data[$this->node->getEntityTypeId()][] = $this->node->id();
    $cookie_data = Json::encode($cookie_data);
    $session->setCookie('Drupal_visitor_like', $cookie_data);

    $this->drupalGet('node/' . $this->node->id());
    $assert_session->pageTextContains('You liked this');
    $assert_session->pageTextContains('(1) Likes');

    $page = $session->getPage();
    $page->find('css', '.like-txt')->click();
    $assert_session->pageTextContains('Like');
    $assert_session->pageTextContains('(0) Likes');
  }

  /**
   * Tests unlike as authenticated user.
   */
  public function testUnLikeAuthenticated() {
    $like = Like::create([
      'entity_type' => $this->node->getEntityTypeId(),
      'entity_id' => $this->node->id(),
      'uid' => $this->authenticatedUser->id(),
      'value' => 1,
    ]);
    $like->save();

    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet('node/' . $this->node->id());
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session->pageTextContains('You liked this');
    $assert_session->pageTextContains('(1) Likes');

    $page->find('css', '.like-txt')->click();
    $assert_session->pageTextContains('Like');
    $assert_session->pageTextContains('(0) Likes');
  }

}
