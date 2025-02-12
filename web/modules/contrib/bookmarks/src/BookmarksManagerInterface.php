<?php

namespace Drupal\bookmarks;

interface BookmarksManagerInterface {

  /**
   * Act on the content of a bookmark entity build.
   */
  public function build(array $build);

  /**
   * Post process the Bookmarks view after its execution.
   */
  public function postRenderList(array $build);
}
