<?php

namespace Drupal\Tests\service\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\service\Entity\Service;
use Drupal\service\Entity\ServiceType;
use Drupal\service\ServiceHierarchy;
use Drupal\service\ServiceInterface;

/**
 * Provides service storage and the transaction mutex for kernel tests.
 */
abstract class ServiceKernelTestBase extends KernelTestBase {

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
    $this->installSchema('service', ['service_hierarchy_lock']);
    $this->container->get('database')->insert('service_hierarchy_lock')->fields(['id' => 1])->execute();
    $this->installEntitySchema('user');
    $this->installEntitySchema('service');
    $this->installConfig(['system', 'user']);
    ServiceType::create(['id' => 'work', 'label' => 'Work'])->save();
    $this->hierarchy = $this->container->get('service.hierarchy');
  }

  /**
   * Creates a saved service with an optional parent.
   */
  protected function createService(?ServiceInterface $parent = NULL): ServiceInterface {
    $service = Service::create(['type' => 'work', 'label' => 'Work', 'service' => $parent]);
    $service->save();
    return $service;
  }

}
