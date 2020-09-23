<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;

/**
 * Interface for open vocabulary association entity storage classes.
 */
interface OpenVocabularyAssociationStorageInterface extends ConfigEntityStorageInterface {

  /**
   * Loads association entities that are related to a specific field.
   *
   * @param string $field_id
   *   The field ID.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface[]
   *   The loaded associations, ordered by weight.
   */
  public function loadAssociationsByField(string $field_id): array;

  /**
   * Loads association entities that target a specific open vocabulary.
   *
   * @param string $vocabulary_id
   *   The vocabulary ID.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface[]
   *   The loaded associations, ordered by weight.
   */
  public function loadAssociationsByVocabulary(string $vocabulary_id): array;

}
