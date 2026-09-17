<?php

namespace Drupal\service\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\service\ServiceInterface;
use Drupal\task\Event\TaskReadinessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Contributes immediate-service gates to task readiness.
 */
class ServiceTaskReadinessSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [TaskReadinessEvent::class => 'onReadiness'];
  }

  /**
   * Evaluates the immediate service without changing tasks or services.
   */
  public function onReadiness(TaskReadinessEvent $event): void {
    $task = $event->getTask();
    if ($task->get('service')->isEmpty()) {
      return;
    }
    $id = $task->get('service')->target_id;
    $service = $id === NULL ? NULL : $this->entityTypeManager->getStorage('service')->loadUnchanged($id);
    if (!$service) {
      $event->addReason('waiting', 'service_missing', ['target_id' => $id]);
    }
    elseif ($service->getStatus() === ServiceInterface::STATUS_DRAFT) {
      $event->addReason('pending', 'service_draft', ['target_id' => $id]);
    }
    elseif ($service->getStatus() !== ServiceInterface::STATUS_ACTIVE) {
      $event->addReason('waiting', 'service_inactive', [
        'target_id' => $id,
        'status' => $service->getStatus(),
      ]);
    }
  }

}
