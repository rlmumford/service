<?php

namespace Drupal\service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\service\Exception\InvalidServiceHierarchyException;

/**
 * Serializes hierarchy writes and validates against current database rows.
 */
class HierarchyWriteGuard {

  /**
   * Constructs the write guard.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The primary database connection used by entity storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param iterable<\Drupal\service\HierarchyScopePolicyInterface> $scopePolicies
   *   Installation-specific scope policies; every policy must permit the link.
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected iterable $scopePolicies = [],
  ) {}

  /**
   * Acquires the shared mutex until the outermost transaction ends.
   */
  public function lock(): void {
    if (!$this->database->inTransaction()) {
      throw new \LogicException('Hierarchy writes require an active storage transaction.');
    }
    // An UPDATE locks SQLite as well as databases supporting row locks.
    // It has no lease to expire and remains held through nested transactions.
    $this->database->update('service_hierarchy_lock')
      ->fields(['id' => 1])->condition('id', 1)->execute();
    if (!$this->database->select('service_hierarchy_lock', 'l')
      ->fields('l', ['id'])->condition('id', 1)->forUpdate()
      ->execute()->fetchField()) {
      throw new \LogicException('The service hierarchy lock is not initialized. Run database updates.');
    }
  }

  /**
   * Requires fresh entity reads for permission and scope decisions on MySQL.
   */
  public function assertFreshReads(): void {
    if ($this->database->driver() !== 'mysql') {
      return;
    }
    // MySQL and MariaDB expose this session setting under different names.
    $isolation = $this->database->query("SHOW SESSION VARIABLES LIKE 'transaction_isolation'")->fetchField(1);
    if ($isolation === FALSE) {
      $isolation = $this->database->query("SHOW SESSION VARIABLES LIKE 'tx_isolation'")->fetchField(1);
    }
    if ($isolation === FALSE || strtoupper($isolation) !== 'READ-COMMITTED') {
      throw new \LogicException('Service move access and scope checks require READ COMMITTED isolation on MySQL/MariaDB.');
    }
  }

  /**
   * Rejects unsaved parents before core can recursively autosave them.
   */
  public function assertSavedParent(ServiceInterface $service): void {
    $reference = $service->get('service')->first();
    if ($reference && $reference->target_id === NULL && !$reference->isEmpty()) {
      throw new InvalidServiceHierarchyException('Save the parent service before attaching it.');
    }
  }

  /**
   * Validates the final proposed service before its fields are written.
   */
  public function validate(ServiceInterface $service): void {
    $this->lock();
    $this->assertSavedParent($service);
    $parent_id = $service->get('service')->target_id;
    $seen = $service->isNew() ? [] : [(string) $service->id() => TRUE];
    $id = $parent_id;
    while ($id !== NULL) {
      if (isset($seen[(string) $id])) {
        throw new InvalidServiceHierarchyException('The service hierarchy contains a cycle.');
      }
      $seen[(string) $id] = TRUE;
      $row = $this->loadRow($id);
      $id = $row->service;
    }
    foreach ($this->scopePolicies as $policy) {
      $this->assertFreshReads();
      $storage = $this->entityTypeManager->getStorage('service');
      if ($parent_id !== NULL) {
        $storage->resetCache([$parent_id]);
      }
      $parent = $parent_id === NULL ? NULL : $storage->loadUnchanged($parent_id);
      if ($parent_id !== NULL && $parent === NULL) {
        throw new InvalidServiceHierarchyException('A referenced service is missing.');
      }
      $policy->validateParent($service, $parent);
    }
  }

  /**
   * Checks a reference within the caller's storage transaction.
   */
  public function validateReference($id): void {
    if ($id !== NULL) {
      $this->lock();
      $this->loadRow($id);
    }
  }

  /**
   * Refuses deletion with current children or tasks, regardless of access.
   *
   * @param array $ids
   *   Service IDs proposed for deletion.
   */
  public function assertDeletable(array $ids): void {
    $this->lock();
    foreach (['service', 'task'] as $type) {
      if (!$this->entityTypeManager->hasDefinition($type)) {
        continue;
      }
      $definition = $this->entityTypeManager->getDefinition($type);
      // These are both single-valued base references in their base tables. Use
      // locking reads, without access or organization query alterations.
      $dependent = $this->database->select($definition->getBaseTable(), 'd')
        ->fields('d', [$definition->getKey('id')])
        ->condition('service', $ids, 'IN')->range(0, 1)->forUpdate()
        ->execute()->fetchField();
      if ($dependent !== FALSE) {
        throw new InvalidServiceHierarchyException('A service with dependent services or tasks cannot be deleted.');
      }
    }
  }

  /**
   * Reads a current service row, bypassing caches and transaction snapshots.
   */
  protected function loadRow($id): object {
    $row = $this->database->select('service', 's')
      ->fields('s', ['id', 'service'])->condition('id', $id)->forUpdate()
      ->execute()->fetchObject();
    if (!$row) {
      throw new InvalidServiceHierarchyException('A referenced service is missing.');
    }
    return $row;
  }

}
