<?php

namespace Drupal\instagram_feeds\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action that can refresh Instagram Token for an entity.
 *
 * @Action(
 *   id = "entity:refresh_instagram_token",
 *   action_label = @Translation("Refresh Token"),
 *   deriver = "Drupal\instagram_feeds\Plugin\Action\Derivative\RefreshInstagramTokenActionDeriver",
 * )
 */
final class RefreshInstagramTokenAction extends EntityActionBase {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * GuzzleHttp\ClientInterface definition.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a RefreshTokenAction object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time,
    ClientInterface $http_client
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->time = $time;
    $this->httpClient = $http_client;
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
      $container->get('datetime.time'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $entity */
    $entity->refreshToken($this->httpClient, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\instagram_feeds\Entity\InstagramAccountInterface $object */
    if (!$account) {
      $account = \Drupal::currentUser();
    }

    // Token is invalid or entity is unpublished.
    if (!$object->isPublished() || !$object->tokenIsValid()) {
      $result = AccessResult::forbidden('Unpublished or Instagram token is invalid.')->addCacheableDependency($object);
      return $return_as_object ? $result : $result->isAllowed();
    }

    $primary_access = $account->hasPermission('administer instagram_feeds');
    $role_access = $primary_access || $account->hasPermission('update instagram_account');
    $user_access = $role_access || $account->id() == $object->getOwnerId() && $account->hasPermission('update own instagram_account');
    // User doesn't have access.
    if (!$user_access) {
      $result = AccessResult::forbidden()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    // Token can be refreshed only after 24h. It lives 60 days. Expired token
    // cannot be refreshed anymore. 60 days - 1 day = 59 days = 5097600 sec.
    $age = $object->getTokenExpirationTime() - $this->time->getRequestTime();
    if ($age <= 5097600) {
      $result = AccessResult::allowed()->setCacheMaxAge($age)->cachePerPermissions()->addCacheableDependency($object);
      return $return_as_object ? $result : $result->isAllowed();
    }
    $age -= 5097600;
    $result = AccessResult::forbidden('You should wait at least 24 hours prior previous token refesh')->setCacheMaxAge($age)->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
