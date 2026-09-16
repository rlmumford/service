<?php

namespace Drupal\service;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for CounselKit service entities.
 */
interface ServiceInterface extends ContentEntityInterface {

  /**
   * Get the service type.
   *
   * @return \Drupal\service\ServiceTypeInterface
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
   * @return integer|string
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
   */
  public function getRecipientIds();

}
