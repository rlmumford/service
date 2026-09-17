<?php

namespace Drupal\service;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Drupal\service\EventSubscriber\ServiceTaskReadinessSubscriber;

/**
 * Registers service integrations for optional modules.
 */
class ServiceServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['task'])) {
      $container->setDefinition('service.task_readiness_subscriber', new Definition(
        ServiceTaskReadinessSubscriber::class,
        [new Reference('entity_type.manager')],
      ))->addTag('event_subscriber')->setPublic(TRUE);

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
