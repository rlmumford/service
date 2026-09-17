<?php

namespace Drupal\Tests\service\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\service\Entity\Service;
use Drupal\service\ServiceInterface;

/**
 * Tests service status storage and independent child statuses.
 *
 * @group service
 */
class ServiceStatusTest extends ServiceKernelTestBase {

  /**
   * New services start in draft and parent changes do not change children.
   */
  public function testStatusesAndIndependentChildren(): void {
    $parent = $this->createService();
    $child = $this->createService($parent);
    $this->assertSame(ServiceInterface::STATUS_DRAFT, $parent->getStatus());
    foreach (array_keys(Service::statusOptionsList()) as $status) {
      $parent->set('status', $status)->save();
      $storage = $this->container->get('entity_type.manager')->getStorage('service');
      $storage->resetCache();
      $this->assertSame($status, $storage->load($parent->id())->getStatus());
      $this->assertSame(ServiceInterface::STATUS_DRAFT, $storage->load($child->id())->getStatus());
    }
  }

  /**
   * Trusted direct saves also reject missing and unsupported statuses.
   */
  public function testInvalidStatuses(): void {
    $service = $this->createService();
    foreach ([NULL, '', 'finished'] as $status) {
      $service->set('status', $status);
      try {
        $service->save();
        $this->fail('Storage accepted an invalid status.');
      }
      catch (EntityStorageException $exception) {
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception->getPrevious());
      }
      $this->assertSame(ServiceInterface::STATUS_DRAFT, Service::load($service->id())->getStatus());
    }
  }

}
