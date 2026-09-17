<?php

namespace Drupal\service;

/**
 * Supplies installation-specific boundaries for service hierarchy writes.
 */
interface HierarchyScopePolicyInterface {

  /**
   * Validates a proposed parent link under the hierarchy write lock.
   *
   * Policies must check any affected subtree when their scope rules require it.
   * They must not mutate or save entities. Register implementations with the
   * service.hierarchy_scope_policy tag; no policies means an unscoped install.
   *
   * @param \Drupal\service\ServiceInterface $service
   *   The proposed service, including unsaved field changes.
   * @param \Drupal\service\ServiceInterface|null $parent
   *   The parent service, or NULL when detaching or saving a root.
   *
   * @throws \Drupal\service\Exception\InvalidServiceHierarchyException
   *   When the proposed relationship would violate the installation's scope.
   */
  public function validateParent(ServiceInterface $service, ?ServiceInterface $parent): void;

}
