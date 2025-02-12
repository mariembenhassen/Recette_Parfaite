<?php

namespace Drupal\like\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\like\Form\LikeForm;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribe to ajax refresh event to enable the like interaction.
 *
 * Like interactions are disabled by the like.js library, so we don't have
 * inconsistencies when people try to speed run click the buttons.
 * Because the ajax request might take a second or two, the buttons will be
 * unavailable for that period and this subscriber re-enables them.
 */
class AjaxRefreshLikeSubscriber implements EventSubscriberInterface {

  /**
   * On response callback.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    if (!$response instanceof AjaxResponse) {
      return;
    }

    $attachments = $response->getAttachments();
    if (empty($attachments) || empty($attachments['drupalSettings']['ajax'])) {
      return;
    }

    foreach ($attachments['drupalSettings']['ajax'] as $attachment) {
      if (isset($attachment['callback']) && is_array($attachment['callback'])) {
        foreach ($attachment['callback'] as $callback) {
          if ($callback instanceof LikeForm) {
            $response->addCommand(new InvokeCommand(
              '.like--wrapper',
              'css',
              ['pointer-events', 'auto']));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];

    return $events;
  }

}
