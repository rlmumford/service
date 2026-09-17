<?php

namespace Drupal\service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\service\Exception\InvalidServiceHierarchyException;
use Drupal\service\Plugin\Field\FieldType\ServiceReferenceItem;

/**
 * Resolves service ancestry without recursively evaluating computed properties.
 *
 * This is an internal data API, like entity storage. Callers must check access
 * before exposing returned entities. Persisted references use current default
 * revisions; the supplied starting service may be unsaved.
 */
class ServiceHierarchy {

  /**
   * Constructs the hierarchy resolver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Returns a service and its ancestors, ordered from itself to its root.
   *
   * @param \Drupal\service\ServiceInterface $service
   *   The starting service, including any unsaved parent change on that entity.
   *
   * @return \Drupal\service\ServiceInterface[]
   *   The inclusive ancestry, with numerically indexed entries.
   *
   * @throws \Drupal\service\Exception\InvalidServiceHierarchyException
   *   When a cycle or missing parent is encountered.
   */
  public function getAncestry(ServiceInterface $service): array {
    $ancestry = [];
    $seen = [];
    do {
      $key = $service->isNew() ? 'new:' . spl_object_id($service) : 'id:' . $service->id();
      if (isset($seen[$key])) {
        throw new InvalidServiceHierarchyException('The service hierarchy contains a cycle.');
      }
      $seen[$key] = TRUE;
      $ancestry[] = $service;
      $reference = $service->get('service')->first();
      $service = $reference ? $this->resolveReference($reference) : NULL;
    } while ($service !== NULL);
    return $ancestry;
  }

  /**
   * Returns the root of a service, including the service itself.
   *
   * @param \Drupal\service\ServiceInterface $service
   *   The starting service.
   *
   * @return \Drupal\service\ServiceInterface
   *   The last service in its ancestry.
   *
   * @throws \Drupal\service\Exception\InvalidServiceHierarchyException
   *   When a cycle or missing parent is encountered.
   */
  public function getRoot(ServiceInterface $service): ServiceInterface {
    $ancestry = $this->getAncestry($service);
    return end($ancestry);
  }

  /**
   * Returns a reference's ancestry, or an empty list for an empty reference.
   *
   * @param \Drupal\service\Plugin\Field\FieldType\ServiceReferenceItem $reference
   *   A service reference field item.
   *
   * @return \Drupal\service\ServiceInterface[]
   *   The referenced service followed by its ancestors.
   *
   * @throws \Drupal\service\Exception\InvalidServiceHierarchyException
   *   When a cycle or missing service is encountered.
   */
  public function getReferencedAncestry(ServiceReferenceItem $reference): array {
    $service = $this->resolveReference($reference);
    return $service === NULL ? [] : $this->getAncestry($service);
  }

  /**
   * Resolves a reference without reusing a previously loaded target object.
   *
   * @param \Drupal\service\Plugin\Field\FieldType\ServiceReferenceItem $reference
   *   The reference to resolve.
   *
   * @return \Drupal\service\ServiceInterface|null
   *   The service, or NULL for an empty reference.
   *
   * @throws \Drupal\service\Exception\InvalidServiceHierarchyException
   *   When a referenced service no longer exists.
   */
  protected function resolveReference(ServiceReferenceItem $reference): ?ServiceInterface {
    $id = $reference->target_id;
    if ($id !== NULL) {
      $service = $this->entityTypeManager->getStorage('service')->load($id);
      if ($service === NULL) {
        throw new InvalidServiceHierarchyException('A referenced service is missing.');
      }
      return $service;
    }
    // Unsaved references have no identifier. They can still be traversed safely
    // using object identity for cycle detection, without saving any entities.
    return $reference->get('entity')->getValue();
  }

}
