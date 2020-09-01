<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Traits;

use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\open_vocabularies\OpenVocabularyInterface;

/**
 * Provides helper methods to create vocabulary and association entities.
 */
trait VocabularyCreationTrait {

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

}
