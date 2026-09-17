<?php

namespace Drupal\service\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\service\Exception\InvalidServiceHierarchyException;

/**
 * Base form handler for services.
 */
class ServiceForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    try {
      return parent::save($form, $form_state);
    }
    catch (EntityStorageException $exception) {
      // Validation is a preflight check; another writer or a presave hook can
      // still invalidate the relationship before storage performs its checks.
      if (!$exception->getPrevious() instanceof InvalidServiceHierarchyException) {
        throw $exception;
      }
      $this->messenger()->addError($this->t('The service could not be saved. @reason', [
        '@reason' => $exception->getPrevious()->getMessage(),
      ]));
      $form_state->setRebuild();
      return NULL;
    }
  }

}
