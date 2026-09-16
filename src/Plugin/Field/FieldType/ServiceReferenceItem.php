<?php

namespace Drupal\service\Plugin\Field\FieldType;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;


/**
 * Defines the 'service_reference' entity field type.
 *
 * @FieldType(
 *   id = "service_reference",
 *   label = @Translation("Service Reference"),
 *   description = @Translation("An entity field containing a service reference."),
 *   category = @Translation("Reference"),
 *   default_widget = "entity_reference_autocomplete",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class ServiceReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'target_type' => 'service',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['all'] = ListDataDefinition::create('entity_reference')
      ->setLabel(t('All services'))
      ->setDescription(new TranslatableMarkup('All services in the chain.'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE)
      ->setItemDefinition(DataReferenceDefinition::create('entity')
        ->setTargetDefinition(EntityDataDefinition::create('service'))
        ->addConstraint('EntityType', 'service')
      );

    $properties['root'] = DataReferenceDefinition::create('entity')
      ->setLabel(t('Root Service'))
      ->setDescription(new TranslatableMarkup('The root service of the referenced service'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE)
      ->setTargetDefinition(EntityDataDefinition::create('service'))
      ->addConstraint('EntityType', 'service');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    parent::onChange($property_name, $notify);

    $this->setChainAndRoot();
  }

  /**
   * {@inheritdoc}
   */
  protected function writePropertyValue($property_name, $value) {
    parent::writePropertyValue($property_name, $value);

    if ($property_name == 'entity') {
      $this->setChainAndRoot();
    }
  }

  /**
   * Set the chain and parent.
   */
  protected function setChainAndRoot() {
    $chain = [];
    $chain[] = $root = $entity = $this->get('entity')->getValue();
    while (isset($entity->service) && !$entity->service->isEmpty() && $entity = $entity->service->entity) {
      $chain[] = $root = $entity;
    }

    if (!empty($chain)) {
      $this->writePropertyValue('all', $chain);
      $this->writePropertyValue('root', $root);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);
    $element['target_type']['#default_value'] = 'service';
    $element['target_type']['#disabled'] = TRUE;

    return $element;
  }
}

