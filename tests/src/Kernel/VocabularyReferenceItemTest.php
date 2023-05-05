<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\Plugin\Field\FieldType\VocabularyReferenceItem;
use Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemList;
use Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Tests the open_vocabulary_reference field type.
 *
 * @group open_vocabularies
 */
class VocabularyReferenceItemTest extends FieldKernelTestBase {

  use VocabularyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'open_vocabularies',
    'open_vocabularies_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
    $field_item_list = $entity->get('field_test');
    $this->assertEquals([], $field_item_list->getValue());
    // Verify the value of computed properties.
    $this->assertNull($field_item_list->entity);
    $this->assertNull($field_item_list->association);

    $vocabulary = $this->createVocabulary();
    $association = $this->createVocabularyAssociation($vocabulary->id());
    // Create an entity to reference.
    $referenced = EntityTest::create();
    $referenced->save();

    $field_item_list->target_association_id = $association->id();
    $field_item_list->target_id = $referenced->id();
    $this->assertInstanceOf(VocabularyReferenceItem::class, $field_item_list->first());
    $this->assertEquals($association->id(), $field_item_list->first()->target_association_id);
    $this->assertEquals($referenced->id(), $field_item_list->first()->target_id);
    // Verify that the computed properties return the correct values.
    $this->assertSameEntity($association, $field_item_list->first()->association);
    $this->assertSameEntity($referenced, $field_item_list->first()->entity);

    // Verify that values are correctly persisted upon saving.
    $entity = $this->entitySaveReload($entity);
    $field_item_list = $entity->get('field_test');
    $this->assertEquals($association->id(), $field_item_list->first()->target_association_id);
    $this->assertEquals($referenced->id(), $field_item_list->first()->target_id);
    $this->assertSameEntity($association, $field_item_list->first()->association);
    $this->assertSameEntity($referenced, $field_item_list->first()->entity);

    // Test that computed properties are properly updated when the related
    // ID properties change.
    // This covers the ::onChange() method when called from a single field item.
    $association_two = $this->createVocabularyAssociation($vocabulary->id());
    $field_item_list->first()->target_association_id = $association_two->id();
    $this->assertSameEntity($association_two, $field_item_list->first()->association);
    $referenced_two = EntityTest::create();
    $referenced_two->save();
    $field_item_list->first()->target_id = $referenced_two->id();
    $this->assertSameEntity($referenced_two, $field_item_list->first()->entity);

    // Test that the ::setValue() method calls correctly the ::onChange() to
    // update the computed properties.
    // First cover the scenario when only an association is passed.
    $association_three = $this->createVocabularyAssociation($vocabulary->id());
    $field_item_list->first()->setValue([
      'target_association_id' => $association_three->id(),
    ]);
    $this->assertSameEntity($association_three, $field_item_list->first()->association);

    // Cover the scenario where both values are passed.
    $field_item_list->first()->setValue([
      'target_association_id' => $association->id(),
      'target_id' => $referenced->id(),
    ]);
    $this->assertSameEntity($association, $field_item_list->first()->association);
    $this->assertSameEntity($referenced, $field_item_list->first()->entity);

    // Test the ::setValue() method with non-array values.
    try {
      $field_item_list->first()->setValue($referenced->id());
      $this->fail('An exception was expected when using scalar values.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(\InvalidArgumentException::class, $e);
      $this->assertEquals('Both association ID and target entity ID are required.', $e->getMessage());
    }

    // Test that the association should always be passed as value.
    try {
      $field_item_list->first()->setValue([
        'target_id' => $referenced->id(),
      ]);
      $this->fail('An exception was expected the association value is not passed.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(\InvalidArgumentException::class, $e);
      $this->assertEquals('No association provided.', $e->getMessage());
    }

    // Test the ::isEmpty() method.
    $field_item_list->first()->setValue([
      'target_association_id' => $association->id(),
      'target_id' => $referenced->id(),
    ]);
    $this->assertFalse($field_item_list->first()->isEmpty());

    // Empty string values are not considered empty.
    $field_item_list->setValue([
      'target_association_id' => '',
      'target_id' => '',
    ]);
    $this->assertFalse($field_item_list->first()->isEmpty());

    // Test that, if any of the two properties are set as NULL, the item is
    // considered empty.
    // We keep the empty strings as values so we make sure that no empty() is
    // used in the ::isEmpty() method.
    $field_item_list->target_id = NULL;
    $this->assertTrue($field_item_list->first()->isEmpty());
    $field_item_list->setValue([
      'target_association_id' => NULL,
      'target_id' => '',
    ]);
    $this->assertTrue($field_item_list->first()->isEmpty());
    $field_item_list->target_id = NULL;
    $this->assertTrue($field_item_list->first()->isEmpty());

    // Test that the value can be set to an empty array.
    $field_item_list->first()->setValue([]);
    $this->assertEquals([], $field_item_list->first()->getValue());
  }

  /**
   * Tests the "open_vocabulary_entity_reference" data type.
   */
  public function testVocabularyEntityReferenceDataType(): void {
    // Create an entity to reference.
    $referenced = EntityTest::create();
    $referenced->save();
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();
    $field_item_list = $entity->get('field_test');

    // Test that null is returned on non-existing associations.
    $field_item_list->appendItem([
      'target_association_id' => $this->randomMachineName(),
      // Pass a correct entity ID so we know the failure happens earlier.
      'target_id' => $referenced->id(),
    ]);
    $this->assertNull($field_item_list->entity);

    // Create a vocabulary and an association.
    $vocabulary = $this->createVocabulary();
    $association = $this->createVocabularyAssociation($vocabulary->id());
    // Set the values to the field.
    $field_item_list->first()->setValue([
      'target_association_id' => $association->id(),
      'target_id' => $referenced->id(),
    ]);

    // Mock the storage to mimic a non-existing association.
    $mock_storage = $this->createMock(EntityStorageInterface::class);
    $mock_storage
      ->expects($this->once())
      ->method('load')
      ->with($vocabulary->id())
      ->willReturn(NULL);
    // Save the original entity type manager and mock the entity type manager
    // to return the mocked storage.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $mock_entity_manager = $this->createMock(EntityTypeManagerInterface::class);
    $mock_entity_manager
      ->method('getStorage')
      ->willReturnCallback(function (string $entity_type_id) use ($entity_type_manager, $mock_storage): EntityStorageInterface {
        return $entity_type_id === 'open_vocabulary' ? $mock_storage : $entity_type_manager->getStorage($entity_type_id);
      });
    $this->container->set('entity_type.manager', $mock_entity_manager);

    // Test that null is returned on non-existing vocabularies.
    $this->assertNull($field_item_list->entity);

    // Restore the original entity type manager.
    $this->container->set('entity_type.manager', $entity_type_manager);

    // Mock the reference handler manager to mimic a non existing plugin.
    $original_plugin_manager = $this->container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler');
    $mock_plugin_manager = $this->createMock(VocabularyReferenceHandlerPluginManagerInterface::class);
    $mock_plugin_manager
      ->expects($this->once())
      ->method('getDefinition')
      ->willReturn(NULL);
    $this->container->set('plugin.manager.open_vocabularies.vocabulary_reference_handler', $mock_plugin_manager);

    // Null is returned when a plugin doesn't exist.
    $this->assertNull($field_item_list->entity);

    // Restore the original plugin manager.
    $this->container->set('plugin.manager.open_vocabularies.vocabulary_reference_handler', $original_plugin_manager);

    // Test that the correct entity is returned when all the related entities
    // and plugins exist.
    $this->assertSameEntity($referenced, $field_item_list->entity);
  }

  /**
   * Asserts that two entities are the same.
   *
   * This is a lightweight assertion, as we don't want to check all the values,
   * but just that instance, entity type and ID match.
   * This is to circumvent comparison issues with some services set in the
   * entities when loaded through data types.
   *
   * @param \Drupal\Core\Entity\EntityInterface $expected
   *   The expected entity.
   * @param \Drupal\Core\Entity\EntityInterface $actual
   *   The actual entity.
   */
  protected function assertSameEntity(EntityInterface $expected, EntityInterface $actual): void {
    $this->assertEquals(get_class($expected), get_class($actual));
    $this->assertEquals($expected->getEntityTypeId(), $actual->getEntityTypeId());
    $this->assertEquals($expected->id(), $actual->id());
  }

}
