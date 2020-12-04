<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\TypedData;

use Drupal\Core\TypedData\DataReferenceDefinition;

/**
 * A typed data definition class for defining open vocabulary references.
 */
class VocabularyDataReferenceDefinition extends DataReferenceDefinition {

  /**
   * {@inheritdoc}
   */
  public static function create($target_data_type) {
    $definition['type'] = 'open_vocabulary_' . $target_data_type . '_reference';
    $definition = new static($definition);

    return $definition->setTargetDefinition(\Drupal::typedDataManager()->createDataDefinition($target_data_type));
  }

}
