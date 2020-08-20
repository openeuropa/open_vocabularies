<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;

/**
 * Storage handler for the Open Vocabulary Association entities.
 */
class OpenVocabularyAssociationStorage extends ConfigEntityStorage {

  /**
   * Returns the next vocabulary association weight.
   */
  public function getNextVocabularyAssociationWeight(): int {
    $vocabulary_associations = $this->getSortedItems();
    if (empty($vocabulary_associations)) {
      return 0;
    }

    $last = end($vocabulary_associations);
    $weight = $last->getWeight();

    // If the weight is less than 0, we start with 0 to allow some buffer to
    // items that are potentially pre-shipped.
    return $weight < 0 ? 0 : $weight + 1;
  }

  /**
   * Loads and sorts the vocabulary associations by weight.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface[]
   *   The sorted vocabulary associations.
   */
  public function getSortedItems(): array {
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface[] $vocabulary_associations */
    $vocabulary_associations = $this->loadMultiple();

    if (empty($vocabulary_associations)) {
      return [];
    }

    uasort($vocabulary_associations, function (OpenVocabularyAssociationInterface $a, OpenVocabularyAssociationInterface $b) {
      return ($a->get('weight') <=> $b->get('weight'));
    });

    return $vocabulary_associations;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity) {
    parent::doPreSave($entity);
    if ($entity->isNew()) {
      $entity->set('weight', $this->getNextVocabularyAssociationWeight());
    }
  }

}
