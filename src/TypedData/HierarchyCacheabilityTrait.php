<?php

namespace Drupal\service\TypedData;

use Drupal\Core\Cache\Cache;

/**
 * Attaches hierarchy and reference-owner dependencies to computed properties.
 *
 * Any service save/delete invalidates service list tags. The owner tag also
 * covers a changed task-to-service reference. This deliberately uses a broad
 * dependency rather than recursively invalidating every descendant entity.
 */
trait HierarchyCacheabilityTrait {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      $this->getParent()->getEntity()->getCacheTags(),
      \Drupal::entityTypeManager()->getDefinition('service')->getListCacheTags(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->getParent()->getEntity()->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $reference = $this->getParent();
    $owner = $reference->getEntity();
    if ($owner->isNew() || $reference->get('entity')->isTargetNew()) {
      return 0;
    }
    return $owner->getCacheMaxAge();
  }

}
