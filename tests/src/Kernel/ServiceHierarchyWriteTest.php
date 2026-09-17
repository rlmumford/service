<?php

namespace Drupal\Tests\service\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\UserSession;
use Drupal\user\Entity\Role;
use Drupal\service\Entity\Service;
use Drupal\service\Entity\ServiceType;
use Drupal\service\Exception\InvalidServiceHierarchyException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests protected hierarchy writes, scope policies, and transaction lifetime.
 *
 * @group service
 */
class ServiceHierarchyWriteTest extends ServiceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['service_hierarchy_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if ($this->container->get('database')->driver() === 'mysql') {
      $this->container->get('database')->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
  }

  /**
   * Saved trees reject self-parenting and descendant-parenting.
   */
  public function testRejectCycleOnSave(): void {
    $root = $this->createService();
    $child = $this->createService($root);
    $root->set('service', $child);
    try {
      $root->save();
      $this->fail('The cyclic save must fail.');
    }
    catch (EntityStorageException $exception) {
      $this->assertInstanceOf(InvalidServiceHierarchyException::class, $exception->getPrevious());
    }
    $fresh = $this->container->get('entity_type.manager')->getStorage('service')->loadUnchanged($root->id());
    $this->assertTrue($fresh->get('service')->isEmpty());
  }

  /**
   * Parent changes from presave hooks are validated before writing fields.
   */
  public function testPresaveMutation(): void {
    $service = $this->createService();
    $service->set('label', 'Self parent from hook');
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('cycle');
    $service->save();
  }

  /**
   * Unsaved parent graphs must not trigger recursive autosaves.
   */
  public function testRejectUnsavedParent(): void {
    $parent = Service::create(['type' => 'work']);
    $child = Service::create(['type' => 'work', 'service' => $parent]);
    $parent->set('service', $child);
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Save the parent');
    $child->save();
  }

  /**
   * Dangling parent IDs are rejected even without entity validation.
   */
  public function testRejectMissingParent(): void {
    $service = Service::create(['type' => 'work', 'service' => 9999]);
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('missing');
    $service->save();
  }

  /**
   * A stale object cannot use its old ancestry to introduce an inverse cycle.
   */
  public function testStaleOppositeMove(): void {
    $first = $this->createService();
    $second = $this->createService();
    $stale_second = clone $second;
    $first->set('service', $second)->save();
    $stale_second->set('service', $first);
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('cycle');
    $stale_second->save();
  }

  /**
   * Scope policies apply to low-level entity saves, not just the move API.
   */
  public function testScopePolicy(): void {
    ServiceType::create(['id' => 'other', 'label' => 'Other scope'])->save();
    $parent = Service::create(['type' => 'other', 'label' => 'Other']);
    $parent->save();
    $service = $this->createService();
    $service->set('service', $parent);
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Cross-scope');
    $service->save();
  }

  /**
   * Parent deletion is refused until children have been detached.
   */
  public function testDeletionProtection(): void {
    $parent = $this->createService();
    $child = $this->createService($parent);
    try {
      $parent->delete();
      $this->fail('Deletion with a child must fail.');
    }
    catch (EntityStorageException $exception) {
      $this->assertStringContainsString('dependent', $exception->getMessage());
    }
    $child->set('service', NULL)->save();
    $parent->delete();
    $this->assertNull(Service::load($parent->id()));
    $this->assertNotNull(Service::load($child->id()));
  }

  /**
   * The move operation denies callers without service update access.
   */
  public function testUnauthorizedMove(): void {
    $service = $this->createService();
    $parent = $this->createService();
    $this->expectException(AccessDeniedHttpException::class);
    $this->container->get('service.mover')->reparent($service, $parent, new UserSession(['uid' => 2]));
  }

  /**
   * Moves check parent access and refresh stale supplied entities.
   */
  public function testAuthorizedMove(): void {
    $service = $this->createService();
    $parent = $this->createService();
    Role::create(['id' => 'move_only', 'label' => 'Move only', 'permissions' => ['update any service']])->save();
    $account = new UserSession(['uid' => 2, 'roles' => ['move_only']]);
    $mover = $this->container->get('service.mover');
    try {
      $mover->reparent($service, $parent, $account);
      $this->fail('The parent must be viewable.');
    }
    catch (AccessDeniedHttpException $exception) {
      $this->assertStringContainsString('denied', $exception->getMessage());
    }
    Role::create([
      'id' => 'move_and_view',
      'label' => 'Move and view',
      'permissions' => ['update any service', 'view any service'],
    ])->save();
    $account = new UserSession(['uid' => 3, 'roles' => ['move_and_view']]);
    $stale = clone $service;
    $service->set('label', 'Preserve this newer label')->save();
    $moved = $mover->reparent($stale, $parent, $account);
    $this->assertSame('Preserve this newer label', $moved->label());
    $this->assertEquals($parent->id(), $moved->get('service')->target_id);
    $detached = $mover->reparent($moved, NULL, $account);
    $this->assertTrue($detached->get('service')->isEmpty());
  }

  /**
   * A deleted destination is an error, never interpreted as a detach request.
   */
  public function testDeletedMoveDestination(): void {
    $service = $this->createService();
    $parent = $this->createService();
    $parent->delete();
    $this->expectException(InvalidServiceHierarchyException::class);
    $this->expectExceptionMessage('missing');
    $this->container->get('service.mover')->reparent($service, $parent, new UserSession(['uid' => 1]));
  }

  /**
   * A competing connection cannot acquire the lock before outer commit.
   */
  public function testLockSurvivesNestedSave(): void {
    $database = $this->container->get('database');
    if ($database->driver() !== 'mysql') {
      $this->markTestSkipped('Row-lock contention is exercised by the MySQL CI job.');
    }
    Database::addConnectionInfo('hierarchy_competitor', 'default', $database->getConnectionOptions());
    $competitor = Database::getConnection('default', 'hierarchy_competitor');
    $competitor->query('SET SESSION innodb_lock_wait_timeout = 1');
    $transaction = $database->startTransaction();
    try {
      $this->createService();
      try {
        $competitor->update('service_hierarchy_lock')->fields(['id' => 1])->condition('id', 1)->execute();
        $this->fail('The inner save must retain its lock until outer commit.');
      }
      catch (\Exception $exception) {
        $this->assertStringContainsString('Lock wait timeout', $exception->getMessage());
      }
      unset($transaction);
      $competitor->update('service_hierarchy_lock')->fields(['id' => 1])->condition('id', 1)->execute();
      $this->assertFalse($database->inTransaction());
    }
    finally {
      Database::removeConnection('hierarchy_competitor');
    }
  }

  /**
   * Rolling back an outer transaction undoes a successfully saved move.
   */
  public function testOuterRollback(): void {
    $first = $this->createService();
    $second = $this->createService();
    $transaction = $this->container->get('database')->startTransaction();
    $first->set('service', $second)->save();
    $transaction->rollBack();
    $storage = $this->container->get('entity_type.manager')->getStorage('service');
    $storage->resetCache();
    $this->assertTrue($storage->load($first->id())->get('service')->isEmpty());
    $second->set('service', $first)->save();
    $this->assertEquals($first->id(), $second->get('service')->target_id);
  }

  /**
   * Scope decisions cannot run against a repeatable-read transaction snapshot.
   */
  public function testScopeRejectsRepeatableRead(): void {
    $database = $this->container->get('database');
    if ($database->driver() !== 'mysql') {
      $this->markTestSkipped('MySQL-specific transaction isolation setting.');
    }
    $database->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('READ COMMITTED');
    $this->createService();
  }

  /**
   * The update installs the mutex without changing existing service data.
   */
  public function testMutexUpdate(): void {
    $service = $this->createService();
    $database = $this->container->get('database');
    $database->schema()->dropTable('service_hierarchy_lock');
    $this->container->get('module_handler')->loadInclude('service', 'install');
    service_update_10001();
    service_update_10001();
    $service->set('label', 'After upgrade')->save();
    $this->assertSame('After upgrade', Service::load($service->id())->label());
    $this->assertSame(1, (int) $database->select('service_hierarchy_lock', 'l')->countQuery()->execute()->fetchField());
  }

}
