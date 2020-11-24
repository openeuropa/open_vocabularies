<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\Plugin\Field\FieldType\VocabularyReferenceItem;
use Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemList;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyCreationTrait;

/**
 * Tests the open_vocabulary_reference field type.
 *
 * @group open_vocabularies
 */
class VocabularyReferenceItemTest extends FieldKernelTestBase {

  use VocabularyCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'open_vocabularies',
    'open_vocabularies_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

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
  }

  /**
   * Tests the item class.
   */
  public function testVocabularyReferenceItem(): void {
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();

    // Assert that the correct list class is used.
    $this->assertInstanceOf(VocabularyReferenceFieldItemList::class, $entity->get('field_test'));
    // Assert that the field definition has the expected properties.
    $this->assertEquals([
      'target_association_id',
      'target_id',
      'entity',
      'association',
    ], array_keys($entity->getFieldDefinition('field_test')->getFieldStorageDefinition()->getPropertyDefinitions()));

    // The field is empty by default.
    $this->assertEquals([], $entity->get('field_test')->getValue());
    // Save the entity and assert that the field is still empty.
    $entity = $this->entitySaveReload($entity);
    $this->assertEquals([], $entity->get('field_test')->getValue());

    $vocabulary = $this->createVocabulary();
    $association = $this->createVocabularyAssociation($vocabulary->id());
    // Create an entity to reference.
    $referenced = EntityTest::create();
    $referenced->save();

    $entity->get('field_test')->target_association_id = $association->id();
    $entity->get('field_test')->target_id = $referenced->id();
    $this->assertInstanceOf(VocabularyReferenceItem::class, $entity->get('field_test')->first());
    $this->assertEquals($association->id(), $entity->get('field_test')->first()->target_association_id);
    $this->assertEquals($referenced->id(), $entity->get('field_test')->first()->target_id);

    // Verify that values are correctly persisted upon saving.
    $entity = $this->entitySaveReload($entity);
    $this->assertEquals($association->id(), $entity->get('field_test')->first()->target_association_id);
    $this->assertEquals($referenced->id(), $entity->get('field_test')->first()->target_id);

    // Test the ::isEmpty() method.
    $this->assertFalse($entity->get('field_test')->first()->isEmpty());
    // Empty string values are not considered empty.
    $entity->get('field_test')->setValue([
      'target_association_id' => '',
      'target_id' => '',
    ]);
    $this->assertFalse($entity->get('field_test')->first()->isEmpty());

    // Test that, if any of the two properties are set as NULL, the item is
    // considered empty.
    // We keep the empty strings as values so we make sure that no empty() is
    // used in the ::isEmpty() method.
    $entity->get('field_test')->target_id = NULL;
    $this->assertTrue($entity->get('field_test')->first()->isEmpty());
    $entity->get('field_test')->setValue([
      'target_association_id' => NULL,
      'target_id' => '',
    ]);
    $this->assertTrue($entity->get('field_test')->first()->isEmpty());
    $entity->get('field_test')->target_id = NULL;
    $this->assertTrue($entity->get('field_test')->first()->isEmpty());
  }

}
