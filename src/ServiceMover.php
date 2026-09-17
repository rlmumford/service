<?php

namespace Drupal\service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\service\Exception\InvalidServiceHierarchyException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides an authorized operation for moving an existing service.
 */
class ServiceMover {

  /**
   * Constructs the move operation.
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected HierarchyWriteGuard $guard,
  ) {}

  /**
   * Moves a service using fresh entities and returns the saved service.
   *
   * Only identifiers are taken from supplied entities, so stale objects cannot
   * overwrite unrelated fields. The caller must be able to update the service
   * and view its proposed parent. Scope policies also apply to trusted saves.
   *
   * @param \Drupal\service\ServiceInterface $service
   *   The saved service to move.
   * @param \Drupal\service\ServiceInterface|null $parent
   *   The saved destination, or NULL to detach.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting the move.
   *
   * @return \Drupal\service\ServiceInterface
   *   The saved service.
   */
  public function reparent(ServiceInterface $service, ?ServiceInterface $parent, AccountInterface $account): ServiceInterface {
    if ($service->isNew() || ($parent && $parent->isNew())) {
      throw new InvalidServiceHierarchyException('Save services before moving them.');
    }
    $transaction = $this->database->startTransaction();
    $storage = $this->entityTypeManager->getStorage('service');
    try {
      $this->guard->assertFreshReads();
      $this->guard->lock();
      $this->guard->validateReference($parent?->id());
      $this->guard->validateReference($service->id());
      $parent_id = $parent?->id();
      $storage->resetCache(array_filter([$service->id(), $parent_id]));
      $service = $storage->loadUnchanged($service->id());
      $parent = $parent_id === NULL ? NULL : $storage->loadUnchanged($parent_id);
      if ($parent_id !== NULL && !$parent) {
        throw new InvalidServiceHierarchyException('The destination service is missing.');
      }
      if (!$service) {
        throw new InvalidServiceHierarchyException('The service to move is missing.');
      }
      $this->entityTypeManager->getAccessControlHandler('service')->resetCache();
      if (!$service->access('update', $account) || ($parent && !$parent->access('view', $account))) {
        throw new AccessDeniedHttpException('Access to move this service is denied.');
      }
      $service->set('service', $parent);
      $service->save();
      return $service;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      $storage->resetCache();
      throw $exception;
    }
  }

}
