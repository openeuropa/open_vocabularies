<?php

declare(strict_types=1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Tests the computed field item list class.
 *
 * @group open_vocabularies
 * @coversDefaultClass \Drupal\open_vocabularies\Plugin\Field\ComputedVocabularyReferenceFieldItemList
 */
class ComputedVocabularyReferenceFieldItemListTest extends FieldKernelTestBase {

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
      'field_name' => 'field_one',
      'entity_type' => 'entity_test',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
    ])->save();
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_two',
      'entity_type' => 'entity_test',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
    ])->save();

    $this->createVocabulary(['id' => 'test_vocabulary']);
    // This association will generate the field "association_one_23fd58145b".
    $this->createVocabularyAssociation('test_vocabulary', [
      'name' => 'association_one',
      'fields' => [
        'entity_test.entity_test.field_one',
      ],
    ]);
    // This association will generate the field "association_two_23fd58145b" and
    // "association_two_bbc05532a4".
    $this->createVocabularyAssociation('test_vocabulary', [
      'name' => 'association_two',
      'fields' => [
        'entity_test.entity_test.field_one',
        'entity_test.entity_test.field_two',
      ],
    ]);
  }

  /**
   * Tests the setValue() method.
   *
   * @covers ::setValue()
   */
  public function testSetValue(): void {
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();

    $entity->get('association_one_23fd58145b')->setValue([1, 2]);
    // The values are stored in the related reference field, ordered by delta.
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 1,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 2,
      ],
    ], $entity->get('field_one')->getValue());

    $entity->get('association_one_23fd58145b')->setValue([3, 2]);
    // The previous values have been cleared up and the new ones are set again
    // with the correct delta.
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 3,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 2,
      ],
    ], $entity->get('field_one')->getValue());

    $entity->get('association_two_23fd58145b')->setValue([10, 15]);
    // The values of this computed field are appended to the existing ones that
    // share the same reference field.
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 3,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 2,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 10,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 15,
      ],
    ], $entity->get('field_one')->getValue());

    $entity->get('association_one_23fd58145b')->setValue(5);
    // The previous values of this computed field are cleared and appended. The
    // Existing values are re-keyed and retain their order.
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 10,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 15,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 5,
      ],
    ], $entity->get('field_one')->getValue());

    // Add values into another computed reference field.
    $entity->get('association_two_bbc05532a4')->setValue([37]);
    // Value are set into the correct reference field.
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 37,
      ],
    ], $entity->get('field_two')->getValue());
    // The other reference field values are not touched.
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 10,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 15,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 5,
      ],
    ], $entity->get('field_one')->getValue());
  }

  /**
   * Tests that computed fields are initialised with the correct data.
   *
   * @covers ::getValue()
   */
  public function testGetValue(): void {
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();

    // Set some values in the vocabulary reference fields.
    // Association values are usually grouped together, given the way
    // ::updateVocabularyReferenceField() works. But we create now a disordered
    // situation to verify that the deltas are properly recalculated in the
    // method ::computeValue().
    $entity->set('field_one', [
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 10,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 5,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 15,
      ],
    ]);
    $entity->set('field_two', [
      [
        'target_association_id' => 'test_vocabulary.association_two',
        'target_id' => 37,
      ],
    ]);

    // The computed fields will contain only the values related to them.
    $this->assertEquals([
      [
        'target_id' => 5,
      ],
    ], $entity->get('association_one_23fd58145b')->getValue());
    $this->assertEquals([
      [
        'target_id' => 10,
      ],
      [
        'target_id' => 15,
      ],
    ], $entity->get('association_two_23fd58145b')->getValue());
    $this->assertEquals([
      [
        'target_id' => 37,
      ],
    ], $entity->get('association_two_bbc05532a4')->getValue());
  }

  /**
   * Tests the remaining methods of writing data into a field.
   *
   * @covers ::onChange()
   * @covers ::appendItem()
   * @covers ::removeItem()
   */
  public function testValueWritingAlternatives(): void {
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();

    // Append an item.
    $entity->get('association_one_23fd58145b')->appendItem(1);
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 1,
      ],
    ], $entity->get('field_one')->getValue());

    // Change directly a property. This is covered by the ::onChange() method.
    $entity->get('association_one_23fd58145b')->target_id = 2;
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 2,
      ],
    ], $entity->get('field_one')->getValue());

    // Similar to the previous test.
    $entity->get('association_one_23fd58145b')->get(0)->target_id = 3;
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 3,
      ],
    ], $entity->get('field_one')->getValue());

    // Changing a specific item value is also covered by ::onChange().
    $entity->get('association_one_23fd58145b')->get(0)->setValue(4);
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 4,
      ],
    ], $entity->get('field_one')->getValue());

    // Set an item with a specific delta. Still covered by ::onChange().
    $entity->get('association_one_23fd58145b')->set(1, 5);
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 4,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 5,
      ],
    ], $entity->get('field_one')->getValue());

    $entity->get('association_one_23fd58145b')->removeItem(0);
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 5,
      ],
    ], $entity->get('field_one')->getValue());

    unset($entity->get('association_one_23fd58145b')->get(0)->entity);
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => NULL,
      ],
    ], $entity->get('field_one')->getValue());
  }

  /**
   * Tests the filter method.
   *
   * @covers ::filter()
   */
  public function testFilter(): void {
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();

    // Fill the entity with some existing data.
    $entity->set('field_one', [
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 7,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 2,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 9,
      ],
    ]);

    // Apply the function to the elements. The values are first computed and
    // then the filtering is applied.
    $entity->get('association_one_23fd58145b')->filter(function ($item): bool {
      return $item->target_id > 5;
    });
    $this->assertEquals([
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 7,
      ],
      [
        'target_association_id' => 'test_vocabulary.association_one',
        'target_id' => 9,
      ],
    ], $entity->get('field_one')->getValue());
  }

}
