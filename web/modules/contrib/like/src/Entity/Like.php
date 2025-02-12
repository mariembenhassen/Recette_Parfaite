<?php

namespace Drupal\like\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Like entity.
 *
 * @ingroup like
 *
 * @ContentEntityType(
 *   id = "like",
 *   label = @Translation("Like"),
 *   label_collection = @Translation("Likes"),
 *   label_singular = @Translation("like"),
 *   label_plural = @Translation("likes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count like",
 *     plural = "@count likes",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "storage_schema" = "Drupal\like\LikeStorageSchema",
 *     "access" = "\Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "list_builder" = "\Drupal\Core\Entity\EntityListBuilder",
 *     "permission_provider" = "\Drupal\entity\UncacheableEntityPermissionProvider",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\entity\Routing\AdminHtmlRouteProvider",
 *       "delete-multiple" = "\Drupal\entity\Routing\DeleteMultipleRouteProvider",
 *     },
 *     "local_task_provider" = {
 *       "default" = "\Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *   },
 *   base_table = "like",
 *   translatable = FALSE,
 *   revisionable = FALSE,
 *   fieldable = FALSE,
 *   show_revision_ui = FALSE,
 *   admin_permission = "administer like",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "owner" = "uid",
 *   },
 *   field_ui_base_route = "entity.like.collection",
 *   links = {
 *     "collection" = "/admin/content/like",
 *     "canonical" = "/admin/content/like/{like}",
 *     "edit-form" = "/admin/content/like/{like}/edit",
 *     "delete-form" = "/admin/content/like/{like}/delete",
 *   },
 * )
 */
class Like extends ContentEntityBase implements LikeInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function getRequestTime() {
    return \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setRequired(TRUE)
      ->setDescription(t('The entity type to which this like is attached.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the entity of the like.'))
      ->setRequired(TRUE);

    $fields['value'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Value'))
      ->setDescription(t('Value of the like (normally 1).'))
      ->setDefaultValue(1)
      ->setRequired(TRUE);

    $fields['timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Timestamp'))
      ->setDescription(t('Timestamp of the like.'))
      ->setDefaultValueCallback(static::class . '::getRequestTime');

    return $fields;
  }

}
