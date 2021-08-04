<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Traits;

use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\open_vocabularies\OpenVocabularyInterface;

/**
 * Provides helper methods for vocabulary and association tests.
 */
trait VocabularyTestTrait {

  /**
   * Creates and returns an Open Vocabulary entity.
   *
   * @param array $values
   *   Entity values to use. When empty, random and standard values are used.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyInterface
   *   The vocabulary entity.
   */
  protected function createVocabulary(array $values = []): OpenVocabularyInterface {
    $values += [
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'description' => $this->randomString(128),
      'handler' => 'test_entity_plugin',
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'entity_test',
        ],
      ],
    ];

    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $vocabulary */
    $vocabulary = OpenVocabulary::create($values);
    $vocabulary->save();

    return $vocabulary;
  }

  /**
   * Creates and returns a vocabulary association entity.
   *
   * @param string $vocabulary_id
   *   The vocabulary ID this association refers to.
   * @param array $values
   *   Entity values to use. When empty, random and standard values are used.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   *   The created vocabulary association entity.
   */
  protected function createVocabularyAssociation(string $vocabulary_id, array $values = []): OpenVocabularyAssociationInterface {
    $values += [
      'label' => $this->randomString(),
      'name' => strtolower($this->randomMachineName()),
      'widget_type' => 'options_select',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
      'fields' => [],
    ];
    $values['vocabulary'] = $vocabulary_id;

    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = OpenVocabularyAssociation::create($values);
    $association->save();

    return $association;
  }

  /**
   * Reloads a vocabulary entity from the database, bypassing caches.
   *
   * @param string $vocabulary_id
   *   The vocabulary ID.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyInterface|null
   *   The reloaded vocabulary entity, or NULL if not found.
   */
  protected function reloadVocabulary(string $vocabulary_id): ?OpenVocabularyInterface {
    \Drupal::configFactory()->reset('open_vocabularies.open_vocabulary.' . $vocabulary_id);

    return \Drupal::entityTypeManager()->getStorage('open_vocabulary')->loadUnchanged($vocabulary_id);
  }

  /**
   * Reloads an association entity from the database, bypassing caches.
   *
   * @param string $association_id
   *   The vocabulary association ID.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface|null
   *   The reloaded vocabulary association entity, or NULL if not found.
   */
  protected function reloadVocabularyAssociation(string $association_id): ?OpenVocabularyAssociationInterface {
    \Drupal::configFactory()->reset('open_vocabularies.open_vocabulary_association.' . $association_id);

    return \Drupal::entityTypeManager()->getStorage('open_vocabulary_association')->loadUnchanged($association_id);
  }

}
