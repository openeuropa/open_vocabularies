<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the access to the entity after adding vocabulary association.
 *
 * @group open_vocabularies
 */
class EntityAccessTest extends KernelTestBase {

  use VocabularyTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'open_vocabularies',
    'open_vocabularies_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installConfig([
      'field',
      'user',
    ]);

    // Create a vocabulary reference field.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
    ])->save();

    // Create a vocabulary.
    $this->createVocabulary([
      'id' => 'vocabulary_test',
      'handler' => 'entity_test_with_bundle',
      'handler_settings' => [
        'target_bundles' => [
          'referenced_bundle' => 'referenced_bundle',
        ],
      ],
    ]);
  }

  /**
   * Tests the entity access.
   *
   * @covers open_vocabularies_entity_access
   */
  public function testEntityAccess(): void {
    // Create the host entity.
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();
    $access = $entity->access('view', NULL, TRUE);

    // Ensure that we don't give access to the user in the access handler.
    $this->assertFalse($access->isAllowed());
    $this->assertEmpty($access->getCacheTags());

    // Create vocabulary association for the entity test.
    $this->createVocabularyAssociation('vocabulary_test', [
      'label' => 'Vocabulary association test',
      'name' => 'vocabulary_association_test',
      'cardinality' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED,
      'fields' => [
        'entity_test.entity_test.field_test',
      ],
    ]);

    // Flush caches after adding association.
    $kernel = \Drupal::service('kernel');
    $kernel->invalidateContainer();
    $kernel->rebuildContainer();

    // Vocabulary association cache tag is added to the entity access object.
    // for "view" operation. Access is forbidden since current user doesn't
    // have access to view the entity.
    $access = $entity->access('view', NULL, TRUE);
    $this->assertEquals(['config:open_vocabularies.open_vocabulary_association.vocabulary_test.vocabulary_association_test'], $access->getCacheTags());
    $this->assertFalse($access->isAllowed());

    // Access handler for "update" and "delete" operations don't add cache tags.
    foreach (['update', 'delete'] as $operation) {
      $access = $entity->access($operation, NULL, TRUE);
      $this->assertEmpty($access->getCacheTags());
      $this->assertFalse($access->isAllowed());
    }

    // User with proper permission is able to view the entity.
    $account = $this->createUser(['view test entity']);
    $access = $entity->access('view', $account, TRUE);
    $this->assertTrue($access->isAllowed());
    $this->assertEquals(['config:open_vocabularies.open_vocabulary_association.vocabulary_test.vocabulary_association_test'], $access->getCacheTags());
  }

}
