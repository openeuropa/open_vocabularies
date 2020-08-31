<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;

/**
 * Defines the 'open_vocabulary_reference' field type.
 *
 * @FieldType(
 *   id = "open_vocabulary_reference",
 *   label = @Translation("Vocabulary reference"),
 *   category = @Translation("Open vocabulary"),
 *   default_widget = "open_vocabulary_reference_widget",
 *   default_formatter = "string",
 *   list_class = "\Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemList"
 * )
 */
class VocabularyReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'target_association' => [
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
        'target_association' => ['target_association'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // @see \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::isEmpty()
    // Avoid loading the entity by first checking the 'target_id'.
    if ($this->target_id !== NULL) {
      return FALSE;
    }
    // @todo reinstate after the property is set in place.
    // if ($this->entity && $this->entity instanceof EntityInterface) {
    //   return FALSE;
    // }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_association'] = DataReferenceTargetDefinition::create('string')
      ->setLabel(t('Association config entity ID'))
      ->setRequired(TRUE);

    $properties['target_id'] = DataReferenceTargetDefinition::create('string')
      ->setLabel(t('Target entity'))
      ->setRequired(TRUE);

    // @todo Define the 'association entity' property, computed field.
    // @todo Define the 'target entity' property, computed field with custom
    // class that loads the target entity after reading association > type
    // config.

    return $properties;
  }

}
