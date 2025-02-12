<?php

namespace Drupal\like\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\like\LikeHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Like form class.
 */
class LikeForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The like helper service.
   *
   * @var \Drupal\like\LikeHelperInterface
   */
  protected LikeHelperInterface $likeHelper;

  /**
   * Constructs a LikeForm object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\like\LikeHelperInterface $like_helper
   *   The like helper service.
   */
  public function __construct(AccountInterface $current_user, LikeHelperInterface $like_helper) {
    $this->currentUser = $current_user;
    $this->likeHelper = $like_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('like.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'like_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FieldItemInterface $item = NULL, array $settings = []) {
    $entity = $item->getEntity();
    $form['#item'] = $item;
    $form['#theme'] = 'like_form';
    $form['#id'] = Html::getUniqueId('like-form-' . $entity->getEntityTypeId() . '-' . $entity->id());

    $form['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
    ];

    $liked = $this->likeHelper->userHasLiked($entity, $this->currentUser);

    // Hidden value to pass the updated likes between the form and js.
    $form['likes'] = [
      '#type' => 'hidden',
      '#default_value' => $item->value,
    ];

    $form['label']['like_toggle'] = [
      '#type' => 'checkbox',
      '#title' => '',
      '#default_value' => $liked,
      '#name' => 'like_toggle',
      '#theme_wrappers' => [],
      '#ajax' => [
        'wrapper' => $form['#id'],
        'callback' => [$this, 'ajaxSubmit'],
        'effect' => 'fade',
        'progress' => ['type' => 'none'],
      ],
    ];
    $form['label']['icon'] = [
      '#type' => 'html_tag',
      '#tag' => 'i',
      '#attributes' => [
        'class' => ['fa-solid', 'fa-heart'],
      ],
    ];
    $form['label']['txt'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $liked ? $settings['liked_state'] : $settings['default_state'],
      '#attributes' => [
        'class' => ['like-txt'],
      ],
    ];
    $form['num'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->likesTxt($item->value),
      '#attributes' => [
        'class' => ['like-num'],
      ],
    ];

    $triggering_element = $form_state->getTriggeringElement();
    if (isset($triggering_element['#name']) && $triggering_element['#name'] == 'like_toggle') {
      $liked = $this->likeHelper->userHasLiked($entity, $this->currentUser);

      $input = $form_state->getUserInput();
      $num_of_likes = (int) $input['likes'] ?? 0;

      // Throw a visual immediate response to the user by adding up the like and
      // displaying the right CTA.
      if (!$liked) {
        $form['label']['txt']['#value'] = $settings['liked_state'];
        $num_of_likes++;
      }
      else {
        $form['label']['txt']['#value'] = $settings['default_state'];
        $num_of_likes--;
      }
      $form['likes']['#value'] = $num_of_likes;
      $form['num']['#value'] = $this->likesTxt($num_of_likes);
      $form['label']['like_toggle']['#value'] = !$liked;
    }

    if (function_exists('antibot_protect_form')) {
      $form['#form_id'] = $form['#id'];
      antibot_protect_form($form);
    }

    return $form;
  }

  /**
   * Ajax callback.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    $item = $form['#item'];
    $entity = $item->getEntity();
    $liked = $this->likeHelper->userHasLiked($entity, $this->currentUser);

    if (!$liked) {
      $this->likeHelper->like($entity, $this->currentUser);
    }
    else {
      $this->likeHelper->unlike($entity, $this->currentUser);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Method intentionally left blank.
  }

  /**
   * Returns the likes text for the item.
   */
  protected function likesTxt(int $value): string {
    return $this->t('(<span>@value</span>) Likes', ['@value' => $value]);
  }

}
