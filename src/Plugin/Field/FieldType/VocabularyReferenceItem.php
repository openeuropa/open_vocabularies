<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field\FieldType;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\open_vocabularies\TypedData\VocabularyDataReferenceDefinition;

/**
 * Defines the 'open_vocabulary_reference' field type.
 *
 * @FieldType(
 *   id = "open_vocabulary_reference",
 *   label = @Translation("Vocabulary reference"),
 *   category = @Translation("Open vocabulary"),
 *   default_widget = "open_vocabulary_reference_widget",
 *   default_formatter = "string",
 *   list_class = "\Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemList",
 *   cardinality = \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
 * )
 */
class VocabularyReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'target_association_id' => [
          'type' => 'varchar_ascii',
          'description' => 'The ID of the association config entity.',
          'length' => 255,
        ],
        'target_id' => [
          'description' => 'The ID of the target entity.',
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
        'target_association_id' => ['target_association_id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // The item is not considered empty only when both targets are not null.
    if ($this->target_id !== NULL && $this->target_association_id !== NULL) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_association_id'] = DataReferenceTargetDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Association config entity ID'))
      ->setRequired(TRUE);

    $properties['target_id'] = DataReferenceTargetDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target entity'))
      ->setRequired(TRUE);

    $properties['entity'] = VocabularyDataReferenceDefinition::create('entity')
      ->setLabel(new TranslatableMarkup('Entity'))
      ->setDescription(new TranslatableMarkup('The referenced entity'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      // Simplify code by not allowing to pass the entity property.
      ->setReadOnly(TRUE);

    $properties['association'] = DataReferenceDefinition::create('entity')
      ->setLabel(new TranslatableMarkup('Vocabulary association'))
      ->setDescription(new TranslatableMarkup('The vocabulary association entity'))
      // The entity object is computed out of the association ID.
      ->setComputed(TRUE)
      // Simplify code by not allowing to pass the association property.
      ->setReadOnly(TRUE)
      ->setTargetDefinition(EntityDataDefinition::create('open_vocabulary_association'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      throw new \InvalidArgumentException('Both association ID and target entity ID are required.');
    }

    parent::setValue($values, FALSE);

    if (!array_key_exists('target_association_id', $values) && array_key_exists('target_id', $values)) {
      throw new \InvalidArgumentException('No association provided.');
    }
    elseif (array_key_exists('target_association_id', $values) && array_key_exists('target_id', $values)) {
      $this->onChange('target_association_id', FALSE);
      $this->onChange('target_id', FALSE);
    }

    // Notify the parent if necessary.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->getName());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    if ($property_name === 'target_association_id') {
      $this->writePropertyValue('association', $this->target_association_id);
      // @todo reset $this->get('entity')->getTargetDefinition()->setEntityTypeId()?
      //   since the possible target entity type is changed?
      // @todo set again the entity property if target_id is defined, to sync
      //   the status of the entity property?
    }
    elseif ($property_name === 'target_id') {
      // Avoid errors from non-set entity type by setting it to empty string.
      // @see \Drupal\Core\Entity\Plugin\DataType\EntityReference::setValue()
      $this->get('entity')->getTargetDefinition()->setEntityTypeId('');
      $this->writePropertyValue('entity', $this->target_id);
    }

    parent::onChange($property_name, $notify);
  }

}
