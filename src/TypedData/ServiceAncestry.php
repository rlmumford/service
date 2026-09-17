<?php

namespace Drupal\service\TypedData;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;

/**
 * Computes a service reference's ancestry when it is read.
 */
class ServiceAncestry extends ItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function ensureComputedValue() {
    // Parent changes do not notify fields on other entities. Recompute so a
    // retained field item sees subsequent saves and deletes.
    $this->computeValue();
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->list = [];
    $ancestry = \Drupal::service('service.hierarchy')->getReferencedAncestry($this->getParent());
    foreach ($ancestry as $delta => $service) {
      $this->list[$delta] = $this->createItem($delta, $service);
    }
  }

}
