<?php

namespace Drupal\service_hierarchy_test;

use Drupal\service\Exception\InvalidServiceHierarchyException;
use Drupal\service\HierarchyScopePolicyInterface;
use Drupal\service\ServiceInterface;

/**
 * Exercises an installation-defined scope using bundle IDs as the boundary.
 */
class TestScopePolicy implements HierarchyScopePolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function validateParent(ServiceInterface $service, ?ServiceInterface $parent): void {
    if ($parent && $service->bundle() !== $parent->bundle()) {
      throw new InvalidServiceHierarchyException('Cross-scope parent links are forbidden.');
    }
  }

}
