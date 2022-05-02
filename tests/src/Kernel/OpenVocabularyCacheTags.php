<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Tests the OpenVocabulary cache tags expiration.
 *
 * @group open_vocabularies
 */
class OpenVocabularyCacheTags extends KernelTestBase {

  use VocabularyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'open_vocabularies',
    'open_vocabularies_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests cache expiration when association is deleted.
   */
  public function testCacheTagsOnAssociationDeletion(): void {
    $vocabulary = $this->createVocabulary();
    $one = $this->createVocabularyAssociation($vocabulary->id(), [
      'fields' => [
        'entity_test.entity_test.field_one',
      ],
      'weight' => 13,
    ]);

    $this->cacheTagsInvalidator = $this->createMock('Drupal\Core\Cache\CacheTagsInvalidatorInterface');
    $this->container->set('cache_tags.invalidator', $this->cacheTagsInvalidator);
    $this->cacheTagsInvalidator->expects($this->any())
      ->method('invalidateTags')
      ->withConsecutive(
         [['config:open_vocabularies.open_vocabulary_association.' . $one->id()]],
         [['entity_test_list']]
      );

    $one->delete();
  }

}
