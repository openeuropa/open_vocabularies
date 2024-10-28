<?php

declare(strict_types=1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the item list of open_vocabulary_reference fields.
 *
 * @coversDefaultClass \Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemList
 * @group open_vocabularies
 */
class VocabularyReferenceFieldItemListTest extends FieldKernelTestBase {

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
   * Tests the method to filter values by association ID.
   *
   * @covers ::filterTargetAssociationItems()
   */
  public function testFilterTargetAssociationItems(): void {
    // Generate 2 association IDs. We don't need real association entities as
    // only the ID is compared in the method.
    $id_to_filter = strtolower($this->randomMachineName());
    $id_to_keep = strtolower($this->randomMachineName());

    $entity = EntityTest::create();
    $entity->get('field_test')->setValue([
      [
        'target_association_id' => $id_to_filter,
      ],
      [
        'target_association_id' => $id_to_keep,
      ],
      [
        'target_association_id' => $id_to_filter,
      ],
      [
        'target_association_id' => $id_to_filter,
      ],
      [
        'target_association_id' => $id_to_keep,
      ],
    ]);

    $entity->get('field_test')->filterTargetAssociationItems($id_to_filter);
    // Items have been re-keyed upon removal.
    $this->assertEquals([
      [
        'target_association_id' => $id_to_keep,
      ],
      [
        'target_association_id' => $id_to_keep,
      ],
    ], $entity->get('field_test')->getValue());
  }

}
