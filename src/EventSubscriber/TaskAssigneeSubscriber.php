<?php

namespace Drupal\service\EventSubscriber;

use Drupal\task\Event\SelectAssigneeEvent;
use Drupal\task\Event\TaskEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TaskAssigneeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc
   */
  public static function getSubscribedEvents(): array {
    $events[TaskEvents::SELECT_ASSIGNEE][] = ['onAssigneeSelect'];
    return $events;
  }

  /**
   * React to assignee selection.
   *
   * @param \Drupal\task\Event\SelectAssigneeEvent $event
   */
  public function onAssigneeSelect(SelectAssigneeEvent $event) {
    $task = $event->getTask();

    $manager = $task->service->entity ? $task->service->entity->manager->entity : NULL;
    if (!$event->getAssignee() && $manager && $manager->isAuthenticated() && $manager->isActive()) {
      $event->setAssignee($manager);
    }
  }
}
