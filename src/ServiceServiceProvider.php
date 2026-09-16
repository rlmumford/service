<?php

namespace Drupal\service;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;

class ServiceServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['task'])) {
      $definition = new Definition(
        'Drupal\service\EventSubscriber\TaskAssigneeSubscriber'
      );
      $definition->addTag('event_subscriber');
      $definition->setPublic(TRUE);
      $container->setDefinition(
        'service.task_assignee_subscriber',
        $definition
      );
    }
  }
}
