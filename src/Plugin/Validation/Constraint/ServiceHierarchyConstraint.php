<?php

namespace Drupal\service\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks service parent relationships before a save is attempted.
 *
 * @Constraint(
 *   id = "ServiceHierarchy",
 *   label = @Translation("Valid service hierarchy", context = "Validation"),
 *   type = "entity:service"
 * )
 */
class ServiceHierarchyConstraint extends Constraint {

  /**
   * The parent validation message.
   *
   * @var string
   */
  public $message = 'Choose a valid parent service. @reason';

}
