<?php

namespace Drupal\service\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\service\Exception\InvalidServiceHierarchyException;
use Drupal\service\HierarchyWriteGuard;
use Drupal\service\ServiceHierarchy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Reports parent errors on the reference widget without acquiring a write lock.
 */
class ServiceHierarchyConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs the validator.
   */
  public function __construct(
    protected ServiceHierarchy $hierarchy,
    protected HierarchyWriteGuard $writeGuard,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('service.hierarchy'), $container->get('service.hierarchy_write_guard'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if ($entity === NULL) {
      return;
    }
    try {
      $this->writeGuard->assertSavedParent($entity);
      $this->hierarchy->getAncestry($entity);
      $this->writeGuard->validateScope($entity);
    }
    catch (InvalidServiceHierarchyException $exception) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('@reason', $exception->getMessage())
        ->atPath('service.0.target_id')
        ->addViolation();
    }
  }

}
