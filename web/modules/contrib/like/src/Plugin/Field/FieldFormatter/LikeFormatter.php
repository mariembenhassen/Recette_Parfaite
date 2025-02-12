<?php

namespace Drupal\like\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'likes_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "like_default",
 *   label = @Translation("Likes"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class LikeFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs an ImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, FormBuilderInterface $form_builder) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();

    foreach ($items as $delta => $item) {
      $elements[$delta]['like'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['like--wrapper'],
          'data-entity-type' => $entity->getEntityTypeId(),
          'data-entity-id' => $entity->id(),
        ],
      ];
      $elements[$delta]['like']['form'] = $this->formBuilder->getForm('\Drupal\like\Form\LikeForm', $item, $this->getSettings());
    }

    $elements['#attached']['library'][] = 'like/like';
    $elements['#attached']['drupalSettings']['like'][$entity->getEntityTypeId() . ':' . $entity->id()] = [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ] + $this->getSettings();

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [];

    $settings['default_state'] = 'Like';
    $settings['liked_state'] = 'You liked this.';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['default_state'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default state text'),
      '#description' => $this->t('Text to show next to the like icon by default.'),
      '#default_value' => $this->getSetting('default_state'),
    ];
    $form['liked_state'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Liked state text'),
      '#description' => $this->t('Text to show next to the like icon when liked.'),
      '#default_value' => $this->getSetting('liked_state'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Default state text: @default_state', [
      '@default_state' => $this->getSetting('default_state'),
    ]);
    $summary[] = $this->t('Liked state text: @liked_state', [
      '@liked_state' => $this->getSetting('liked_state'),
    ]);
    return $summary;
  }

}
