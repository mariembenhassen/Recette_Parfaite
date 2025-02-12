<?php

namespace Drupal\like\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures like button settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'like_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['like.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('like.settings');
    $entity_types = array_filter($this->entityTypeManager->getDefinitions(), fn($e) => $e->getGroup() === 'content');
    $options = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $options[$entity_type_id] = $entity_type->getLabel();
    }
    asort($options);
    // Disallow likes on likes.
    unset($options['like']);

    $form['enabled_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable the like button for the following entity types:'),
      '#description' => $this->t('Configuration for each one can be done on the display settings.'),
      '#options' => $options,
      '#default_value' => $config->get('enabled_entity_types') ?? [],
    ];

    $form['like_cookie_expiry_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Like Cookie Expiry Time(in seconds)'),
      '#description' => $this->t('Please enter the time in seconds after which the like cookie will expire.'),
      '#required' => TRUE,
      '#default_value' => $config->get('like_cookie_expiry_time'),
    ];

    $form['cache_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Cache type'),
      '#description' => $this->t('Type of cache used depending on the volume of content and likes.'),
      '#options' => [
        'entity' => $this->t('Entity based cache (default).'),
        'time' => $this->t('Time based cache. Recommended for high volume sites.'),
      ],
      '#default_value' => $config->get('cache_type') ?? 'entity',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('like.settings');
    $config->set('enabled_entity_types', $form_state->getValue('enabled_entity_types'));
    $config->set('like_cookie_expiry_time', (int) $form_state->getValue('like_cookie_expiry_time'));
    $config->set('cache_type', $form_state->getValue('cache_type'));
    $config->save();
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

}
