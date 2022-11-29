<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\open_vocabularies\Plugin\Field\ComputedVocabularyReferenceFieldItemList;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Tests the hooks implemented by the fields manager class.
 *
 * @coversDefaultClass \Drupal\open_vocabularies\VocabularyReferenceFieldsManager
 */
class VocabularyReferenceFieldsManagerTest extends FieldKernelTestBase {

  use VocabularyTestTrait;

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

    // Create a field present only in a bundle, that will not be targeted
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

    // Create two different vocabularies. We need static IDs so we can rely on
    // the expected names for the generated fields.
    $this->createVocabulary([
      'id' => 'vocabulary_one',
      'handler' => 'entity_test_with_bundle',
      'handler_settings' => [
        'target_bundles' => [
          'referencable_one' => 'referencable_one',
          'referencable_two' => 'referencable_two',
        ],
      ],
    ]);
    $this->createVocabulary([
      'id' => 'vocabulary_two',
    ]);
  }

  /**
   * Tests that field definitions are created based on associations.
   *
   * @covers ::entityBundleFieldInfoAlter()
   */
  public function testEntityBundleFieldInfoAlter(): void {
    // Collect all the existing field definitions for the various bundles.
    $field_manager = $this->container->get('entity_field.manager');
    $existing_definitions = [
      'bundle_a' => $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a'),
      'bundle_b' => $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_b'),
    ];

    // Create one association that maps to two fields on the same entity bundle.
    // This makes sure that different computed field names are generated.
    $association_one = $this->createVocabularyAssociation('vocabulary_one', [
      'label' => 'Association one',
      'name' => 'association_one',
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

    // Create an association assigned to:
    // - a field present in two bundles: this makes sure that full ID qualifiers
    //   are used to search for matching associations.
    // - a field that is mapped by an association already: this will make sure
    //   that all the associations related to a field are retrieved and
    //   processed.
    $association_two = $this->createVocabularyAssociation('vocabulary_two', [
      'label' => 'Association two',
      'name' => 'association_two',
      'widget_type' => 'options_buttons',
      'required' => FALSE,
      'help_text' => '',
      'predicate' => 'http://example.com/#about',
      'cardinality' => 1,
      'fields' => [
        'entity_test_with_bundle.bundle_b.multiple_bundles',
        'entity_test_with_bundle.bundle_a.only_bundle_a',
      ],
    ]);

    // Prepare the expected handler settings for later usage.
    $vocabulary_one_handler_settings = [
      'target_bundles' => [
        'referencable_one' => 'referencable_one',
        'referencable_two' => 'referencable_two',
      ],
    ];
    $vocabulary_two_handler_settings = [
      'target_bundles' => [
        'entity_test' => 'entity_test',
      ],
    ];

    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a');
    // Three definitions should have been created.
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
        'handler_settings' => $vocabulary_one_handler_settings,
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
        'handler_settings' => $vocabulary_one_handler_settings,
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
        'handler_settings' => $vocabulary_two_handler_settings,
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
        'handler_settings' => $vocabulary_two_handler_settings,
        'open_vocabulary_association' => $association_two->id(),
        'open_vocabulary_reference_field' => 'multiple_bundles',
      ],
      'name' => 'association_two_94ab077978',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_b',
    ], $definitions['association_two_94ab077978']);

    // Change the label and the cardinality of an association.
    $association_two->set('label', $this->randomString());
    $association_two->set('cardinality', 2)->save();

    // Verify that the related field definitions have been updated.
    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a');
    $this->assertComputedFieldDefinition([
      'label' => $association_two->label(),
      'description' => '',
      'required' => FALSE,
      'cardinality' => 2,
      'settings' => [
        'target_type' => 'entity_test',
        'handler' => 'default',
        'handler_settings' => $vocabulary_two_handler_settings,
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
      'cardinality' => 2,
      'settings' => [
        'target_type' => 'entity_test',
        'handler' => 'default',
        'handler_settings' => $vocabulary_two_handler_settings,
        'open_vocabulary_association' => $association_two->id(),
        'open_vocabulary_reference_field' => 'multiple_bundles',
      ],
      'name' => 'association_two_94ab077978',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_b',
    ], $definitions['association_two_94ab077978']);

    // Change the handler settings for the first vocabulary.
    unset($vocabulary_one_handler_settings['target_bundles']['referencable_one']);
    $vocabulary_one = OpenVocabulary::load('vocabulary_one');
    $vocabulary_one->set('handler_settings', $vocabulary_one_handler_settings)->save();

    // The related field definitions should be updated with the new settings.
    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a');
    $this->assertComputedFieldDefinition([
      'label' => 'Association one',
      'description' => 'Help text to serve as description.',
      'required' => TRUE,
      'cardinality' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'entity_test_with_bundle',
        'handler' => 'default',
        'handler_settings' => $vocabulary_one_handler_settings,
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
        'handler_settings' => $vocabulary_one_handler_settings,
        'open_vocabulary_association' => $association_one->id(),
        'open_vocabulary_reference_field' => 'only_bundle_a',
      ],
      'name' => 'association_one_1c8d2512e6',
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'bundle_a',
    ], $definitions['association_one_1c8d2512e6']);

    // Delete the second association and verify that the related definitions
    // have been deleted..
    $association_two->delete();

    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_a');
    $this->assertEquals([
      'association_one_94ab077978',
      'association_one_1c8d2512e6',
    ], array_keys(array_diff_key($definitions, $existing_definitions['bundle_a'])));

    $definitions = $field_manager->getFieldDefinitions('entity_test_with_bundle', 'bundle_b');
    $this->assertEquals(array_keys($existing_definitions['bundle_b']), array_keys($definitions));
  }

  /**
   * Tests that the generated field definitions are placed into the entity form.
   *
   * @covers ::entityFormDisplayAlter()
   */
  public function testEntityFormDisplayAlter(): void {
    $this->createVocabularyAssociation('vocabulary_one', [
      'name' => 'association_one',
      'fields' => [
        'entity_test_with_bundle.bundle_a.multiple_bundles',
      ],
    ]);

    /** @var \Drupal\entity_test\Entity\EntityTestWithBundle $entity */
    $entity = EntityTestWithBundle::create(['type' => 'bundle_a']);

    // Load the form display.
    $render_form_display = EntityFormDisplay::collectRenderDisplay($entity, EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE);

    // Only the default fields are placed in the form display.
    $this->assertEquals([
      'langcode',
      'name',
    ], array_keys($render_form_display->getComponents()));

    // Since no changes were done in the form, the mode has not been changed.
    $this->assertEquals(EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE, $render_form_display->getMode());

    // Place the "multiple_bundles" vocabulary reference field with a specific
    // weight.
    /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $form_display */
    $form_display = $this->container->get('entity_display.repository')->getFormDisplay('entity_test_with_bundle', 'bundle_a');
    $form_display->setComponent('multiple_bundles', [
      'type' => 'open_vocabulary_reference_widget',
      'weight' => -10,
    ]);
    $form_display = $this->entitySaveReload($form_display);

    // Reload the form display that will be used for rendering.
    $render_form_display = EntityFormDisplay::collectRenderDisplay($entity, EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE);
    // The computed field definition should be placed amongst the visible
    // components, while the original vocabulary reference field should be
    // now hidden.
    $this->assertEquals([
      'langcode',
      'name',
      'association_one_94ab077978',
    ], array_keys($render_form_display->getComponents()));

    // Verify that the field is using the correct widget specified in the
    // association. We use the subset assertion as the component has extra
    // settings key which we don't set and are defaulted.
    $this->assertEquals([
      'type' => 'options_select',
      'weight' => -10,
      'region' => 'content',
      'settings' => [],
      'third_party_settings' => [],
    ], $render_form_display->getComponent('association_one_94ab077978'));

    // Create another vocabulary association, pointing to two fields of the
    // entity.
    $this->createVocabularyAssociation('vocabulary_one', [
      'name' => 'association_two',
      'widget_type' => 'entity_reference_autocomplete',
      'fields' => [
        'entity_test_with_bundle.bundle_a.multiple_bundles',
        'entity_test_with_bundle.bundle_a.only_bundle_a',
      ],
    ]);

    $render_form_display = EntityFormDisplay::collectRenderDisplay($entity, EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE);
    // The second association generated two computed fields, but only one is
    // placed into the form display as the related vocabulary reference field
    // has no widget placed into the form yet.
    $this->assertEquals([
      'langcode',
      'name',
      'association_one_94ab077978',
      'association_two_94ab077978',
    ], array_keys($render_form_display->getComponents()));

    $this->assertEquals([
      'type' => 'options_select',
      'weight' => -10,
      'region' => 'content',
      'settings' => [],
      'third_party_settings' => [],
    ], $render_form_display->getComponent('association_one_94ab077978'));
    $this->assertEquals([
      'type' => 'entity_reference_autocomplete',
      'weight' => -9.999,
      'region' => 'content',
      'settings' => [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
        'size' => 60,
        'placeholder' => '',
      ],
      'third_party_settings' => [],
    ], $render_form_display->getComponent('association_two_94ab077978'));

    // Place the "only_bundle_a" field into the form display into a custom
    // region.
    $form_display->setComponent('only_bundle_a', [
      'type' => 'open_vocabulary_reference_widget',
      'weight' => 0,
      'region' => 'custom_region',
    ]);
    $form_display->save();

    $render_form_display = EntityFormDisplay::collectRenderDisplay($entity, EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE);
    $this->assertEquals([
      'langcode',
      'name',
      'association_one_94ab077978',
      'association_two_94ab077978',
      'association_two_1c8d2512e6',
    ], array_keys($render_form_display->getComponents()));

    // The field has inherited the region from the vocabulary reference field
    // widget.
    $this->assertEquals([
      'type' => 'entity_reference_autocomplete',
      'weight' => 0.001,
      'region' => 'custom_region',
      'settings' => [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
        'size' => 60,
        'placeholder' => '',
      ],
      'third_party_settings' => [],
    ], $render_form_display->getComponent('association_two_1c8d2512e6'));

    // Create another vocabulary association and set its weight higher than
    // the others.
    $this->createVocabularyAssociation('vocabulary_one', [
      'name' => 'association_three',
      'widget_type' => 'options_buttons',
      'fields' => [
        'entity_test_with_bundle.bundle_a.multiple_bundles',
      ],
    ])->set('weight', -20)->save();

    $render_form_display = EntityFormDisplay::collectRenderDisplay($entity, EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE);
    $this->assertEquals([
      'langcode',
      'name',
      'association_three_94ab077978',
      'association_one_94ab077978',
      'association_two_94ab077978',
      'association_two_1c8d2512e6',
    ], array_keys($render_form_display->getComponents()));

    $this->assertEquals([
      'type' => 'options_buttons',
      'weight' => -10.02,
      'region' => 'content',
      'settings' => [],
      'third_party_settings' => [],
    ], $render_form_display->getComponent('association_three_94ab077978'));
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
