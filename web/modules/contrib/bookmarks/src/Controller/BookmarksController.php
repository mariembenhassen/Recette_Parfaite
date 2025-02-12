<?php

namespace Drupal\bookmarks\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\views\Views;

/**
 * Returns responses for bookmarks module routes.
 */
class BookmarksController extends ControllerBase {

  /**
   * Route title callback.
   *
   * @return string
   *   The page title.
   */
  public function title() {
    return $this->t('Bookmarks');
  }

  /**
   * Presents the list page.
   *
   * @return array
   *   A form array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function build() {
    $args = [];

    // Filter view by current user;
    $args[0] = $this->currentUser()->id();

    $build = [];
    if ($view = Views::getView('bookmarks')) {
      $view->setArguments($args);
      $view->execute();
      $build['list'] = $view->render('default');
      $build['list']['#pre_render'][] = [\Drupal::service('bookmarks.manager'), 'postRenderList'];
    } else {
      $build['list'] = ['#markup' => $this->t('Missing view: bookmarks')];
    }

    return $build;
  }

  /**
   * Access callback for bookmarks page.
   *
   * @param \Drupal\user\UserInterface $user
   *   (optional) The owner of the bookmarks set.
   * 
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(UserInterface $user = NULL) {
    $account = $this->currentUser();
    $is_same = $user && ($account->id() == $user->id());
    return AccessResult::allowedIf($is_same || $account->hasPermission('access others bookmarks'))
      ->cachePerPermissions()
      ->cachePerUser();
  }
}
