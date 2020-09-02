<?php

declare(strict_types=1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\open_vocabularies\Plugin\Field\ComputedVocabularyReferenceFieldItemList;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyCreationTrait;

/**
 * Tests the hooks implemented by the fields manager class.
 *
 * @coversDefaultClass \Drupal\open_vocabularies\VocabularyReferenceFieldsManager
 */
class VocabularyReferenceFieldsManagerTest extends FieldKernelTestBase {

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

    $this->installEntitySchema('entity_test_with_bundle');

    // Create bundles that will be used as referenced bundles.
    EntityTestBundle::create(['id' => 'referencable_one'])->save();
    EntityTestBundle::create(['id' => 'referencable_two'])->save();

    // Create two bundles that will be used for hosting the fields.
    EntityTestBundle::create(['id' => 'bundle_a'])->save();
    EntityTestBundle::create(['id' => 'bundle_b'])->save();

    // Create a field storage that will be used for two field instances.
    $storage = FieldStorageConfig::create([
      'field_name' => 'multiple_bundles',
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'bundle_a',
    ])->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'bundle_b',
    ])->save();

    // Create a field present only in a bundle.
    $storage = FieldStorageConfig::create([
      'field_name' => 'only_bundle_a',
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'bundle_a',
    ])->save();

    // Create a field present only in a bundle, that will not be targetted
    // by any vocabularies.
    $storage = FieldStorageConfig::create([
      'field_name' => 'only_bundle_b',
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'bundle_b',
    ])->save();
  }

  /**
   * Tests that base field definitions are created based on associations.
   *
   * @covers ::entityBundleFieldInfoAlter()
   */
  public function testEntityBundleFieldInfoAlter(): void {
    // Create two different vocabularies. We need static IDs so we can rely on
    // the expected names for the generated fields.
    $vocabulary_one = $this->createVocabulary([
      'id' => 'vocabulary_one',
      'handler' => 'entity_test_with_bundle',
      'handler_settings' => [
        'target_bundles' => [
          'referencable_one' => 'referencable_one',
          'referencable_two' => 'referencable_two',
        ],
      ],
    ]);
    $vocabulary_two = $this->createVocabulary([
      'id' => 'vocabulary_two',
    ]);

    // Collect all the existing field definitions for the various bundles.
    $field_manager = $this->container->get('entity_field.manager');
    $existing_definitions = [
      'bundle_a' => $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a'),
      'bundle_b' => $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_b'),
    ];

    // Create one association that maps to two fields on the same entity bundle.
    // This makes sure that different computed field names are generated.
    $association_one = OpenVocabularyAssociation::create([
      'label' => 'Association one',
      'name' => 'association_one',
      'vocabulary' => $vocabulary_one->id(),
      'widget_type' => 'options_select',
      'required' => TRUE,
      'help_text' => 'Help text to serve as description.',
      'predicate' => 'http://example.com/#contain',
      'cardinality' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED,
      'fields' => [
        'entity_test_with_bundle.bundle_a.multiple_bundles',
        'entity_test_with_bundle.bundle_a.only_bundle_a',
      ],
    ]);
    /** @var \Drupal\open_vocabularies\Entity\OpenVocabularyAssociation $association_one */
    $association_one->save();

    // Create an association assigned to:
    // - a field present in two bundles: this makes sure that full ID qualifiers
    //   are used to search for matching associations.
    // - a field that is mapped by an association already: this will make sure
    //   that all the associations related to a field are retrieved and
    //   processed.
    $association_two = OpenVocabularyAssociation::create([
      'label' => 'Association two',
      'name' => 'association_two',
      'vocabulary' => $vocabulary_two->id(),
      'widget_type' => 'options_buttons',
      'required' => FALSE,
      'predicate' => 'http://example.com/#about',
      'cardinality' => 1,
      'fields' => [
        'entity_test_with_bundle.bundle_b.multiple_bundles',
        'entity_test_with_bundle.bundle_a.only_bundle_a',
      ],
    ]);
    /** @var \Drupal\open_vocabularies\Entity\OpenVocabularyAssociation $association_two */
    $association_two->save();

    $field_manager->clearCachedFieldDefinitions();
    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a');
    // Two definitions should have been created.
    $this->assertEquals([
      'association_one_94ab077978',
      'association_one_1c8d2512e6',
      'association_two_1c8d2512e6',
    ], array_keys(array_diff_key($definitions, $existing_definitions['bundle_a'])));

    $this->assertComputedFieldDefinition([
      'label' => 'Association one',
      'description' => 'Help text to serve as description.',
      'required' => TRUE,
      'cardinality' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'entity_test_with_bundle',
        'handler' => 'default',
        'handler_settings' => $vocabulary_one->getHandlerSettings(),
        'open_vocabulary_association' => $association_one->id(),
        'open_vocabulary_reference_field' => 'multiple_bundles',
      ],
      'name' => 'association_one_94ab077978',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_a',
    ], $definitions['association_one_94ab077978']);

    $this->assertComputedFieldDefinition([
      'label' => 'Association one',
      'description' => 'Help text to serve as description.',
      'required' => TRUE,
      'cardinality' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'entity_test_with_bundle',
        'handler' => 'default',
        'handler_settings' => $vocabulary_one->getHandlerSettings(),
        'open_vocabulary_association' => $association_one->id(),
        'open_vocabulary_reference_field' => 'only_bundle_a',
      ],
      'name' => 'association_one_1c8d2512e6',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_a',
    ], $definitions['association_one_1c8d2512e6']);

    $this->assertComputedFieldDefinition([
      'label' => 'Association two',
      'description' => '',
      'required' => FALSE,
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'entity_test',
        'handler' => 'default',
        'handler_settings' => $vocabulary_two->getHandlerSettings(),
        'open_vocabulary_association' => $association_two->id(),
        'open_vocabulary_reference_field' => 'only_bundle_a',
      ],
      'name' => 'association_two_1c8d2512e6',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_a',
    ], $definitions['association_two_1c8d2512e6']);

    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_b');
    // One definition should have been created for the second bundle.
    $this->assertEquals([
      'association_two_94ab077978',
    ], array_keys(array_diff_key($definitions, $existing_definitions['bundle_b'])));

    $this->assertComputedFieldDefinition([
      'label' => 'Association two',
      'description' => '',
      'required' => FALSE,
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'entity_test',
        'handler' => 'default',
        'handler_settings' => $vocabulary_two->getHandlerSettings(),
        'open_vocabulary_association' => $association_two->id(),
        'open_vocabulary_reference_field' => 'multiple_bundles',
      ],
      'name' => 'association_two_94ab077978',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_b',
    ], $definitions['association_two_94ab077978']);

    // Change the label of an association.
    $association_two->set('label', $this->randomString())->save();
    $field_manager->clearCachedFieldDefinitions();

    // Verify that the related field definitions have been updated.
    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a');
    $this->assertComputedFieldDefinition([
      'label' => $association_two->label(),
      'description' => '',
      'required' => FALSE,
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'entity_test',
        'handler' => 'default',
        'handler_settings' => $vocabulary_two->getHandlerSettings(),
        'open_vocabulary_association' => $association_two->id(),
        'open_vocabulary_reference_field' => 'only_bundle_a',
      ],
      'name' => 'association_two_1c8d2512e6',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_a',
    ], $definitions['association_two_1c8d2512e6']);

    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_b');
    $this->assertComputedFieldDefinition([
      'label' => $association_two->label(),
      'description' => '',
      'required' => FALSE,
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'entity_test',
        'handler' => 'default',
        'handler_settings' => $vocabulary_two->getHandlerSettings(),
        'open_vocabulary_association' => $association_two->id(),
        'open_vocabulary_reference_field' => 'multiple_bundles',
      ],
      'name' => 'association_two_94ab077978',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_b',
    ], $definitions['association_two_94ab077978']);
  }

  /**
   * Asserts values of a computed vocabulary reference base field definition.
   *
   * @param array $expected
   *   The expected values. The required keys are:
   *   - label: the definition label.
   *   - description: the definition description.
   *   - required: if the definition is marked as required or not.
   *   - cardinality: the definition cardinality.
   *   - settings: the definition settings.
   *   - name: the definition field name.
   *   - target_entity_type_id: the definition target entity type ID.
   *   - target_bundle: the definition target bundle.
   * @param \Drupal\Core\Field\BaseFieldDefinition $definition
   *   The base field definition instance.
   */
  protected function assertComputedFieldDefinition(array $expected, BaseFieldDefinition $definition): void {
    // Assert first the attributes common to all fields.
    $this->assertEquals('entity_reference', $definition->getType());
    $this->assertFalse($definition->isTranslatable());
    $this->assertTrue($definition->isComputed());
    $this->assertEquals(ComputedVocabularyReferenceFieldItemList::class, $definition->getClass());
    $this->assertFalse($definition->isReadOnly());
    $this->assertFalse($definition->isDisplayConfigurable('form'));
    $this->assertEquals('open_vocabularies', $definition->getProvider());

    $this->assertEquals($expected['label'], $definition->getLabel());
    $this->assertEquals($expected['description'], $definition->getDescription());
    $this->assertEquals($expected['required'], $definition->isRequired());
    $this->assertEquals($expected['cardinality'], $definition->getCardinality());
    $this->assertEquals($expected['settings'], $definition->getSettings());
    $this->assertEquals($expected['name'], $definition->getName());
    $this->assertEquals($expected['target_entity_type_id'], $definition->getTargetEntityTypeId());
    $this->assertEquals($expected['target_bundle'], $definition->getTargetBundle());
  }

}
