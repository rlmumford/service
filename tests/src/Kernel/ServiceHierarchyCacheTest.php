<?php

namespace Drupal\Tests\service\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\service\Entity\Service;

/**
 * Tests computed hierarchy metadata and actual cache invalidation after writes.
 *
 * @group service
 */
class ServiceHierarchyCacheTest extends ServiceKernelTestBase {

  /**
   * Parent moves invalidate cached roots without saving descendants.
   */
  public function testMoveInvalidatesComputedProperties(): void {
    $root = $this->createService();
    $other_root = $this->createService();
    $parent = $this->createService($root);
    $child = $this->createService($parent);
    $cache = $this->container->get('cache.render');
    $reference = $child->get('service')->first();
    foreach (['root', 'all'] as $name) {
      $metadata = CacheableMetadata::createFromObject($reference->get($name));
      $this->assertContains('service_list', $metadata->getCacheTags());
      $this->assertContains('service:' . $child->id(), $metadata->getCacheTags());
      $this->assertSame(Cache::PERMANENT, $metadata->getCacheMaxAge());
      $cache->set($name, 'cached hierarchy', Cache::PERMANENT, $metadata->getCacheTags());
      $this->assertNotFalse($cache->get($name));
    }
    $parent->set('service', $other_root)->save();
    $this->assertFalse($cache->get('root'));
    $this->assertFalse($cache->get('all'));
    $this->assertSame($other_root->id(), $reference->get('root')->getTargetIdentifier());
  }

  /**
   * Empty and unsaved references still have meaningful cache dependencies.
   */
  public function testEmptyAndUnsavedReferences(): void {
    $owner = $this->createService();
    $item = $owner->get('service')->appendItem();
    $this->assertSame([], $item->get('all')->getValue());
    $this->assertNull($item->get('root')->getValue());
    foreach (['root', 'all'] as $name) {
      $this->assertContains('service_list', $item->get($name)->getCacheTags());
      $this->assertSame(Cache::PERMANENT, $item->get($name)->getCacheMaxAge());
    }
    $unsaved = Service::create(['type' => 'work']);
    $owner->set('service', $unsaved);
    foreach (['root', 'all'] as $name) {
      $this->assertSame(0, $owner->get('service')->first()->get($name)->getCacheMaxAge());
    }
    $unsaved->set('service', $this->createService());
    $this->assertSame(0, $unsaved->get('service')->first()->get('root')->getCacheMaxAge());
  }

}
