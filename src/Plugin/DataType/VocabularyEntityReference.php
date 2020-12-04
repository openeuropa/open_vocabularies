<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\DataType;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;

/**
 * Defines an "open_vocabulary_entity_reference" data type.
 *
 * This serves as "entity" property of vocabulary reference field items.
 *
 * @DataType(
 *   id = "open_vocabulary_entity_reference",
 *   label = @Translation("Open vocabulary entity reference"),
 *   definition_class = "\Drupal\open_vocabularies\TypedData\VocabularyDataReferenceDefinition"
 * )
 */
class VocabularyEntityReference extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function getTarget() {
    if (!isset($this->target) && isset($this->id)) {
      $association = $this->getParent()->get('association')->getValue();
      // If the association is not found, return null early.
      if (!isset($association)) {
        $this->target = NULL;
        return $this->target;
      }

      // Load the vocabulary.
      $vocabulary = \Drupal::entityTypeManager()->getStorage('open_vocabulary')
        ->load($association->getVocabulary());
      // If the vocabulary is not found, return null early.
      if (!isset($vocabulary)) {
        $this->target = NULL;
        return $this->target;
      }

      $definition = \Drupal::service('plugin.manager.open_vocabularies.vocabulary_reference_handler')
        ->getDefinition($vocabulary->getHandler());
      // If the reference plugin is not found, return null early.
      if (!isset($definition)) {
        $this->target = NULL;
        return $this->target;
      }

      $this->getTargetDefinition()->setEntityTypeId($definition['target_type']);
      return parent::getTarget();
    }

    return $this->target;
  }

}
