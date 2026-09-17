<?php

namespace Drupal\service\TypedData;

use Drupal\Core\Cache\CacheableDependencyInterface;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;

/**
 * Computes a service reference's root when it is read.
 */
class ServiceRoot extends EntityReference implements CacheableDependencyInterface {

  use HierarchyCacheabilityTrait;

  /**
   * {@inheritdoc}
   */
  public function getTarget() {
    $ancestry = \Drupal::service('service.hierarchy')->getReferencedAncestry($this->getParent());
    return $ancestry ? end($ancestry)->getTypedData() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetIdentifier() {
    return $this->getValue()?->id();
  }

  /**
   * {@inheritdoc}
   */
  public function isTargetNew() {
    return $this->getValue()?->isNew() ?? FALSE;
  }

}
