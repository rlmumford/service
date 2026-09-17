<?php

namespace Drupal\Tests\service\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\service\Entity\Service;
use Drupal\service\Entity\ServiceType;
use Drupal\service\Exception\InvalidServiceHierarchyException;
use Drupal\service\ServiceHierarchy;
use Drupal\service\ServiceInterface;

/**
 * Tests lazy service ancestry through entity storage and typed data.
 *
 * @group service
 */
class ServiceHierarchyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'entity', 'views', 'service'];

  /**
   * The ancestry resolver.
   *
   * @var \Drupal\service\ServiceHierarchy
   */
  protected ServiceHierarchy $hierarchy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('service');
    $this->installConfig(['system', 'user']);
    ServiceType::create(['id' => 'work', 'label' => 'Work'])->save();
    $this->hierarchy = $this->container->get('service.hierarchy');
  }

  /**
   * Ancestry includes the service; reference properties start at its parent.
   */
  public function testAncestryAndTypedData(): void {
    $root = $this->createService();
    $parent = $this->createService($root);
    $child = $this->createService($parent);
    $this->assertSame([$child->id(), $parent->id(), $root->id()], $this->ids($this->hierarchy->getAncestry($child)));
    $this->assertSame($root->id(), $this->hierarchy->getRoot($child)->id());
    $this->assertSame($root->id(), $this->hierarchy->getRoot($root)->id());

    $reference = $child->get('service')->first();
    $all = $reference->get('all');
    $this->assertSame([$parent->id(), $root->id()], $this->ids($all->getValue()));
    $this->assertCount(2, $all);
    $this->assertFalse($all->isEmpty());
    $this->assertSame($parent->id(), $all->first()->getTargetIdentifier());
    $this->assertSame($root->id(), $all->get(1)->getTarget()->getValue()->id());
    $this->assertCount(2, iterator_to_array($all));
    $this->assertSame($root->id(), $reference->get('root')->getTargetIdentifier());
    $this->assertSame($root->id(), $reference->get('root')->getTarget()->getValue()->id());
    $this->assertFalse($reference->get('root')->isTargetNew());
  }

  /**
   * Clearing a retained reference clears its already instantiated properties.
   */
  public function testEmptyAndClearedReference(): void {
    $root = $this->createService();
    $child = $this->createService($root);
    $reference = $child->get('service')->first();
    $all = $reference->get('all');
    $root_property = $reference->get('root');
    $this->assertCount(1, $all);
    $this->assertNotNull($root_property->getValue());
    $reference->setValue(NULL);
    $this->assertSame([], $all->getValue());
    $this->assertTrue($all->isEmpty());
    $this->assertCount(0, $all);
    $this->assertNull($all->first());
    $this->assertNull($root_property->getValue());
    $this->assertNull($root_property->getTargetIdentifier());
    $this->assertFalse($root_property->isTargetNew());
    $this->assertSame($child->id(), $this->hierarchy->getRoot($child)->id());
  }

  /**
   * Saved moves are visible through retained properties and cached references.
   */
  public function testReparentAfterReading(): void {
    $root = $this->createService();
    $other_root = $this->createService();
    $parent = $this->createService($root);
    $child = $this->createService($parent);
    $reference = $child->get('service')->first();
    // Populate core's entity-reference target cache as well as our properties.
    $this->assertSame($parent->id(), $reference->entity->id());
    $all = $reference->get('all');
    $root_property = $reference->get('root');
    $this->assertSame($root->id(), $root_property->getTargetIdentifier());
    $this->assertCount(2, $all);

    $storage = $this->container->get('entity_type.manager')->getStorage('service');
    $moved = $storage->loadUnchanged($parent->id());
    $moved->set('service', $other_root)->save();
    $this->assertSame($other_root->id(), $root_property->getTargetIdentifier());
    $this->assertSame([$parent->id(), $other_root->id()], $this->ids($all->getValue()));
    $moved->set('service', NULL)->save();
    $this->assertSame($parent->id(), $root_property->getTargetIdentifier());
    $this->assertCount(1, $all);
  }

  /**
   * Unsaved graphs can be inspected without triggering recursive saves.
   */
  public function testUnsavedServices(): void {
    $root = Service::create(['type' => 'work', 'label' => 'New root']);
    $child = Service::create(['type' => 'work', 'service' => $root]);
    $this->assertSame([$child, $root], $this->hierarchy->getAncestry($child));
    $this->assertSame($root, $child->get('service')->first()->get('root')->getValue());
    $this->assertTrue($child->get('service')->first()->get('root')->isTargetNew());
    $this->assertTrue($root->isNew());
    $this->assertTrue($child->isNew());
    $root->set('service', $child);
    $this->expectException(InvalidServiceHierarchyException::class);
    $this->hierarchy->getAncestry($child);
  }

  /**
   * Self references fail explicitly instead of hanging while being assigned.
   */
  public function testSelfReference(): void {
    $service = $this->createService();
    $service->set('service', $service);
    $this->expectException(InvalidServiceHierarchyException::class);
    $this->hierarchy->getRoot($service);
  }

  /**
   * Stored cycles are detected across different entity object instances.
   */
  public function testStoredCycle(): void {
    $first = $this->createService();
    $second = $this->createService($first);
    $first->set('service', $second)->save();
    $this->container->get('entity_type.manager')->getStorage('service')->resetCache();
    $this->expectException(InvalidServiceHierarchyException::class);
    $second->get('service')->first()->get('all')->getValue();
  }

  /**
   * Dangling references are errors, not a truncated ancestry or an empty root.
   */
  public function testMissingReferencedService(): void {
    $service = Service::create(['type' => 'work', 'service' => 9999]);
    $this->expectException(InvalidServiceHierarchyException::class);
    $service->get('service')->first()->get('root')->getValue();
  }

  /**
   * Deleting a previously read ancestor cannot leave a stale computed root.
   */
  public function testMissingAncestorAfterReading(): void {
    $root = $this->createService();
    $parent = $this->createService($root);
    $child = $this->createService($parent);
    $root_property = $child->get('service')->first()->get('root');
    $this->assertSame($root->id(), $root_property->getTargetIdentifier());
    $root->delete();
    $this->expectException(InvalidServiceHierarchyException::class);
    $root_property->getValue();
  }

  /**
   * Creates a saved service with an optional parent.
   */
  protected function createService(?ServiceInterface $parent = NULL): ServiceInterface {
    $service = Service::create(['type' => 'work', 'label' => 'Work', 'service' => $parent]);
    $service->save();
    return $service;
  }

  /**
   * Extracts IDs while preserving ancestry order.
   */
  protected function ids(array $services): array {
    return array_map(fn(ServiceInterface $service) => $service->id(), $services);
  }

}
