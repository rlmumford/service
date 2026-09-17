<?php

namespace Drupal\service;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for service entities.
 */
interface ServiceInterface extends ContentEntityInterface {

  public const STATUS_DRAFT = 'draft';
  public const STATUS_ACTIVE = 'active';
  public const STATUS_COMPLETE = 'complete';
  public const STATUS_CANCELLED = 'cancelled';
  public const STATUS_SUPERSEDED = 'superseded';

  /**
   * Gets the lifecycle status, or NULL if explicitly cleared before saving.
   *
   * A status is required when saving.
   */
  public function getStatus(): ?string;

  /**
   * Get the service type.
   *
   * @return \Drupal\service\ServiceTypeInterface
   *   The service type.
   */
  public function getType();

  /**
   * Get the manager entity.
   *
   * @return \Drupal\user\UserInterface
   *   The user managing the service.
   */
  public function getManager();

  /**
   * Get the manager id.
   *
   * @return int|string
   *   The user id
   */
  public function getManagerId();

  /**
   * Get the recipients.
   *
   * @return \Drupal\user\UserInterface[]
   *   A list of user entities of the recipients.
   */
  public function getRecipients();

  /**
   * Get the main recipient.
   *
   * @return \Drupal\user\UserInterface
   *   The main recipient
   */
  public function getMainRecipient();

  /**
   * Get the recipient ids.
   *
   * @return string[]|int[]
   *   The recipient user IDs.
   */
  public function getRecipientIds();

}
