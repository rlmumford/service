<?php

namespace Drupal\service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Enforces service hierarchy integrity inside entity storage transactions.
 */
class ServiceStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity) {
    $guard = \Drupal::service('service.hierarchy_write_guard');
    $guard->lock();
    $guard->assertSavedParent($entity);
    return parent::doPreSave($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    // Validate after all presave hooks, including changes they made to parent.
    \Drupal::service('service.hierarchy_write_guard')->validate($entity);
    parent::doSaveFieldItems($entity, $names);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    \Drupal::service('service.hierarchy_write_guard')->assertDeletable(array_keys($entities));
    parent::doDelete($entities);
  }

}
