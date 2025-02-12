<?php

namespace Drupal\bookmarks\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagLinkBuilderInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'cerp_bookmark_block' block.
 *
 * @Block(
 *   id = "cerp_bookmark_block",
 *   admin_label = @Translation("Bookmark"),
 *   category = @Translation("User")
 * )
 */
class BookmarkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The flag Service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The builder for flag links.
   *
   * @var \Drupal\flag\FlagLinkBuilderInterface
   */
  protected $flagLinkBuilder;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The current path object.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The current language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * Build our block.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\flag\FlagServiceInterface $flag
   *   The flag Service.
   * @param \Drupal\flag\FlagLinkBuilderInterface $flag_link_builder
   *   The flag link builder Service.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path stack.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The alias manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    FlagServiceInterface $flag_service,
    FlagLinkBuilderInterface $flag_link_builder,
    CurrentRouteMatch $current_route_match,
    CurrentPathStack $current_path,
    AliasManagerInterface $alias_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->flagService = $flag_service;
    $this->flagLinkBuilder = $flag_link_builder;
    $this->routeMatch = $current_route_match;
    $this->currentPath = $current_path;
    $this->aliasManager = $alias_manager;
    $this->langcode = $language_manager->getCurrentLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('flag'),
      $container->get('flag.link_builder'),
      $container->get('current_route_match'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($flag = $this->flagService->getFlagById('bookmark')) {
      return $flag->access('create', $account, TRUE);
    }
    
    return AccessResult::forbidden('flag:bookmark not found');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $path = $this->currentPath->getPath();
    $alias = $this->aliasManager->getAliasByPath($path);
    $langcode = $this->langcode;

    $storage = $this->entityTypeManager->getStorage('path_alias');
    $pids = $storage->getQuery()
      ->condition('alias', $alias, '=')
      ->condition('path', $path, '=')
      ->condition('langcode', $langcode, '=')
      ->accessCheck(FALSE)
      ->execute();

    // Create a path_alias on-the-fly (e.g. link created from module).
    if (empty($pids)) {
      $alias = $storage->create([
        'alias' => $alias,
        'path' => $path,
        'langcode' => $langcode,
      ]);
      if ($alias->save()) {
        $pids[] = $alias->id();
      }
    }

    $pid = reset($pids);
    if ($alias = $pid ? $storage->load($pid) : NULL) {
      $flag = $this->flagService->getFlagById('bookmark');
      $link = $this->flagLinkBuilder->build($alias->getEntityTypeId(), $alias->id(), $flag->id());
      $build['flag'] = $link;
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // @todo Can it be cached?
    return 0;
  }
}
