<?php

namespace Drupal\service\Exception;

/**
 * Signals a cycle or missing parent in a service hierarchy.
 */
class InvalidServiceHierarchyException extends \RuntimeException {}
