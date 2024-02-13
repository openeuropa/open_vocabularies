<?php

declare(strict_types=1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Tests for the open vocabulary association storage class.
 *
 * @group open_vocabularies
 * @coversDefaultClass \Drupal\open_vocabularies\OpenVocabularyAssociationStorage
 */
class OpenVocabularyAssociationStorageTest extends KernelTestBase {

  use VocabularyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'open_vocabularies',
    'open_vocabularies_test',
  ];

  /**
   * Tests the method to load associations given a field ID.
   *
   * @covers ::loadAssociationsByField()
   */
  public function testLoadAssociationsByField(): void {
    $vocabulary = $this->createVocabulary();
    $one = $this->createVocabularyAssociation($vocabulary->id(), [
      'fields' => [
        'entity_test.entity_test.field_one',
      ],
      'weight' => 13,
    ]);
    $two = $this->createVocabularyAssociation($vocabulary->id(), [
      'fields' => [
        'entity_test.entity_test.field_one',
        'entity_test.entity_test.field_two',
      ],
      'weight' => -50,
    ]);
    $three = $this->createVocabularyAssociation($vocabulary->id(), [
      'fields' => [
        'entity_test.entity_test.field_three',
      ],
      'weight' => 0,
    ]);

    $storage = $this->container->get('entity_type.manager')->getStorage('open_vocabulary_association');

    $this->assertEquals([
      $two->id() => $two,
      $one->id() => $one,
    ], $storage->loadAssociationsByField('entity_test.entity_test.field_one'));

    $this->assertEquals([
      $three->id() => $three,
    ], $storage->loadAssociationsByField('entity_test.entity_test.field_three'));

    $this->assertEquals([], $storage->loadAssociationsByField('entity_test.entity_test.not_existing'));
  }

  /**
   * Tests the method to load associations given a vocabulary ID.
   *
   * @covers ::loadAssociationsByVocabulary()
   */
  public function testLoadAssociationsByVocabulary(): void {
    $vocabulary_one = $this->createVocabulary();
    $vocabulary_two = $this->createVocabulary();
    $one = $this->createVocabularyAssociation($vocabulary_one->id());
    $two = $this->createVocabularyAssociation($vocabulary_two->id());
    $three = $this->createVocabularyAssociation($vocabulary_one->id(), [
      'weight' => -50,
    ]);

    $storage = $this->container->get('entity_type.manager')->getStorage('open_vocabulary_association');

    $this->assertEquals([
      $three->id() => $three,
      $one->id() => $one,
    ], $storage->loadAssociationsByVocabulary($vocabulary_one->id()));

    $this->assertEquals([
      $two->id() => $two,
    ], $storage->loadAssociationsByVocabulary($vocabulary_two->id()));

    $this->assertEquals([], $storage->loadAssociationsByVocabulary('vocabulary_not_existing'));
  }

}
