<?php

namespace Drupal\bookmarks;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\path_alias\Entity\PathAlias;
use Symfony\Component\HttpFoundation\Request;

/**
 * A custom service to deal with bookmarks.
 */
class BookmarksManager implements BookmarksManagerInterface, TrustedCallbackInterface {

  protected $router;
  protected $request;
  protected $titleResolver;

  /**
   * Constructs our Controller object.
   */
  public function __construct($router, $request, $title_resolver) {
    $this->router = $router;
    $this->request = $request->getCurrentRequest();
    $this->titleResolver = $title_resolver;
  }

  /**
   * {@inheritDoc}
   */
  public function build(array $build) {
    // Get links from bookmark.
    $bookmark = $build['#flagging'];
    $aliases = array_filter($bookmark->referencedEntities(), function ($entity) {
      return $entity instanceof PathAlias;
    });

    // Get custom title, if possible.
    $custom_title = NULL;
    if ($bookmark->hasField('title')) {
      if (!($items = $bookmark->get('title'))->isEmpty()) {
        $custom_title = $items->first()->getString();
      }
    }

    // Prepare links render arrays.
    $build['aliases'] = [];
    foreach ($aliases as $alias) {
      try {
        // Assumes `flagging:bookmark` are always internal.
        $uri = 'internal:' . $alias->getPath();
        $url = Url::fromUri($uri);

        $route_match = $this->router->match($uri);
        $route = $route_match['_route_object'] ?? NULL;

        // Default title resolver.
        if ($route && !$custom_title) {
          try {
            $path = $url->setAbsolute(TRUE)->toString();
            $request = Request::create($path);
            $attributes = $this->router->matchRequest($request);
            $request->attributes->add($attributes);
            $custom_title = $this->titleResolver->getTitle($request, $route);
          } catch (\Exception $e) {
            /* Fail silently. */
          }
        }

        // @todo Recognize front page and set title.
        
        // Get URL from the entity, if possible.
        if ($route && !$custom_title) {
          // Try to get title from route.
          foreach ($route->getOption('parameters') ?? [] as $name => $options) {
            if (isset($options['type']) && strpos($options['type'], 'entity:') === 0) {
              $entity = $route_match[$name] ?? NULL;
              if ($entity instanceof EntityInterface && $entity->getEntityType()->hasLinkTemplate('canonical')) {
                $url = $entity->toUrl('canonical');
                $custom_title = $entity->label();
                break;
              }
            }
          }
        }

        $url->setOption('title', $custom_title ?? t('Bookmark'));
      } catch (\Exception $e) {
        /* Fail silently */
        $url = Url::fromRoute('<nolink>');
        $url->setOption('title', t('Broken'));
      }

      $url->setAbsolute(TRUE);

      $build['aliases'][] = [
        '#type' => 'link',
        '#title' => $url->getOption('title') ?? $url->toString(),
        '#url' => $url,
      ];
    }

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function postRenderList(array $build) {
    $view = $build['#view'];

    if (empty($view->result)) {
      // Hide View form is no results.
      $build['#rows']['actions']['#access'] = FALSE;
    }
    
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['build', 'postRenderList'];
  }
}
