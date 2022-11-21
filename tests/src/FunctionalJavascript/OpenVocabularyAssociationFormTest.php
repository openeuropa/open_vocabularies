<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\Tests\open_vocabularies\Traits\NativeBrowserValidationTrait;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Tests the open vocabulary association entity forms.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAssociationFormTest extends OpenVocabulariesFormTestBase {

  use NativeBrowserValidationTrait;
  use VocabularyTestTrait;

  /**
   * The field instance labels created for this test, keyed by field name.
   *
   * @var \Drupal\field\Entity\FieldConfig[]
   */
  protected $fieldInstances = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a field that has two instances in two bundles.
    $storage = FieldStorageConfig::create([
      'field_name' => strtolower($this->randomMachineName()),
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    $instance = FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'alpha',
      // Prepend an "a" so this field is shown early in lists.
      'label' => 'a' . $this->randomString(),
    ]);
    $instance->save();
    $this->fieldInstances[] = $instance;
    $instance = FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'beta',
      'label' => $this->randomString(),
    ]);
    $instance->save();
    $this->fieldInstances[] = $instance;

    // Create another field for the Alpha bundle, but without instance label.
    // Since the machine name will be used for the field labe, prepend a "b"
    // to it to guarantee the order in the UI.
    $storage = FieldStorageConfig::create([
      'field_name' => 'b' . strtolower($this->randomMachineName()),
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    $instance = FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'alpha',
    ]);
    $instance->save();
    $this->fieldInstances[] = $instance;

    // Create a field attached to the entity_test entity.
    $storage = FieldStorageConfig::create([
      'field_name' => strtolower($this->randomMachineName()),
      'entity_type' => 'entity_test',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    $instance = FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
      'label' => $this->randomString(),
    ]);
    $instance->save();
    $this->fieldInstances[] = $instance;

    // Create a field of another type.
    $storage = FieldStorageConfig::create([
      'field_name' => strtolower($this->randomMachineName()),
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests the create, update and delete routes.
   */
  public function testVocabularyAssociationCreationUpdateDeletion(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabulary associations',
      'access content',
    ]));

    $vocabulary = $this->createVocabulary();

    // Create a vocabulary association.
    $this->drupalGet('/admin/structure/open-vocabulary-association');
    $this->clickLink('Add vocabulary association');

    $assert_session = $this->assertSession();
    $assert_session->fieldExists('Label')->setValue('Association 1');
    // Wait for the machine name to appear.
    $assert_session->waitForText('Machine name: association_1');

    // Verify that the correct fields are marked as required.
    $this->disableNativeBrowserRequiredFieldValidation();
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Select at least one entry from the Fields section.');
    $assert_session->pageTextContains('Widget type field is required.');
    $assert_session->pageTextContains('Vocabulary field is required.');
    $assert_session->pageTextContains('Predicate field is required.');

    // Find the fieldset that wraps all the field checkboxes.
    $fieldset = $assert_session->elementExists('named', ['fieldset', 'Fields']);
    // Find the fieldset wrapping the fields for each entity type.
    $groups = $fieldset->findAll('css', 'fieldset.fieldgroup.form-composite');
    $this->assertCount(2, $groups);
    // Check that the correct order and labels are used for the field groups.
    $this->assertEquals('Test entity', $groups[0]->find('css', 'legend')->getText());
    $this->assertEquals('Test entity with bundle', $groups[1]->find('css', 'legend')->getText());

    // Prepare the expected labels for the fields attached to the Alpha bundle.
    // Since the bundle has two fields, the field name or label will be appended
    // to the checkboxes label.
    $label_first_alpha_field = sprintf('Alpha (%s)', $this->fieldInstances[0]->label());
    $label_second_alpha_field = sprintf('Alpha (%s)', $this->fieldInstances[2]->getName());

    $fields = $groups[0]->findAll('css', 'input[type="checkbox"]');
    $this->assertCount(1, $fields);
    $this->assertEquals('Entity Test Bundle', $fields[0]->find('xpath', './../label')->getText());

    $fields = $groups[1]->findAll('css', 'input[type="checkbox"]');
    $this->assertCount(3, $fields);
    $this->assertEquals($label_first_alpha_field, $fields[0]->find('xpath', './../label')->getText());
    $this->assertEquals($label_second_alpha_field, $fields[1]->find('xpath', './../label')->getText());
    $this->assertEquals('Beta', $fields[2]->find('xpath', './../label')->getText());

    // Select two fields.
    $this->getSession()->getPage()->checkField($label_first_alpha_field);
    $this->getSession()->getPage()->checkField('Entity Test Bundle');

    $this->assertEquals([
      '- Select -' => '- Select -',
      'options_buttons' => 'Check boxes/radio buttons',
      'entity_reference_autocomplete' => 'Autocomplete',
      'options_select' => 'Select list',
      'entity_reference_autocomplete_tags' => 'Autocomplete (Tags style)',
    ], $this->getOptions('Widget type'));
    $this->getSession()->getPage()->selectFieldOption('Widget type', 'Select list');

    $this->assertEquals([
      '- Select -' => '- Select -',
      $vocabulary->id() => $vocabulary->label(),
    ], $this->getOptions('Vocabulary'));
    $this->getSession()->getPage()->selectFieldOption('Vocabulary', $vocabulary->label());

    $this->assertEquals([
      '- Select -' => '- Select -',
      'http://example.com/#contain' => 'Contain',
      'http://example.com/#about' => 'About',
    ], $this->getOptions('Predicate'));
    $this->getSession()->getPage()->selectFieldOption('Predicate', 'Contain');

    $this->assertEquals([
      'Limited',
      'Unlimited',
    ], array_values($this->getOptions('Allowed number of values')));
    $assert_session->fieldValueEquals('Allowed number of values', 'number');
    $assert_session->fieldValueEquals('Limit', 1);

    // Test the cardinality states.
    $this->getSession()->getPage()->selectFieldOption('Allowed number of values', 'Unlimited');
    $this->assertFalse($this->getSession()->getPage()->findField('Limit')->isVisible());
    $this->getSession()->getPage()->selectFieldOption('Allowed number of values', 'Limited');
    $this->assertTrue($this->getSession()->getPage()->findField('Limit')->isVisible());

    $this->getSession()->getPage()->checkField('Required');
    $this->getSession()->getPage()->fillField('Help text', 'A description to help.');

    // Verify that on form rebuilds, caused for example by AJAX actions from
    // modules extending the form, checked checkboxes are not marked as
    // disabled.
    $this->getSession()->getPage()->pressButton('Test rebuild');
    $assert_session->fieldEnabled($label_first_alpha_field);
    $assert_session->fieldEnabled('Entity Test Bundle');

    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Created new vocabulary association Association 1.');

    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = \Drupal::entityTypeManager()->getStorage('open_vocabulary_association')
      ->load($vocabulary->id() . '.association_1');
    $this->assertInstanceOf(OpenVocabularyAssociationInterface::class, $association);
    $this->assertEquals('association_1', $association->getName());
    $this->assertEquals('Association 1', $association->label());
    $this->assertEquals([
      $this->fieldInstances[3]->id(),
      $this->fieldInstances[0]->id(),
    ], $association->getFields());
    $this->assertEquals('options_select', $association->getWidgetType());
    $this->assertEquals($vocabulary->id(), $association->getVocabulary());
    $this->assertEquals(1, $association->getCardinality());
    // @todo update the predicate.
    $this->assertEquals('http://example.com/#contain', $association->getPredicate());
    $this->assertEquals('A description to help.', $association->getHelpText());
    $this->assertTrue($association->isRequired());

    // Edit the vocabulary association.
    $this->clickLink('Edit');
    $assert_session->fieldDisabled('Vocabulary');
    $assert_session->fieldDisabled('Allowed number of values');
    $assert_session->fieldEnabled('Limit');
    $this->getSession()->getPage()->fillField('Limit', 2);
    $assert_session->fieldValueEquals('Label', 'Association 1');
    $assert_session->fieldValueEquals('Widget type', 'options_select');
    $assert_session->fieldValueEquals('Vocabulary', $vocabulary->id());
    // @todo update the predicate.
    $assert_session->fieldValueEquals('Predicate', 'http://example.com/#contain');
    $assert_session->fieldValueEquals('Help text', 'A description to help.');
    $assert_session->checkboxChecked('Required');
    // Verify that the checkboxes for the already selected fields are present
    // but disabled.
    $assert_session->checkboxChecked($label_first_alpha_field);
    $assert_session->fieldDisabled($label_first_alpha_field);
    $assert_session->checkboxChecked('Entity Test Bundle');
    $assert_session->fieldDisabled('Entity Test Bundle');
    // The other checkboxes are not checked nor disabled.
    $assert_session->checkboxNotChecked($label_second_alpha_field);
    $assert_session->fieldEnabled($label_second_alpha_field);
    $assert_session->checkboxNotChecked('Beta');
    $assert_session->fieldEnabled('Beta');

    // Select one extra field.
    $this->getSession()->getPage()->checkField('Beta');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Updated vocabulary association Association 1.');

    // Verify that all the fields are saved. This test assures that already
    // selected fields are retained, as disabled checkboxes are not submitted.
    // It also verifies that all the field identifiers are correctly sorted.
    $association = $this->reloadVocabularyAssociation($vocabulary->id() . '.association_1');
    $this->assertEquals([
      $this->fieldInstances[3]->id(),
      $this->fieldInstances[0]->id(),
      $this->fieldInstances[1]->id(),
    ], $association->getFields());

    // Verify that for existing entities, the field checkboxes are not marked
    // as disabled on form rebuilds.
    $this->clickLink('Edit');
    $this->getSession()->getPage()->checkField($label_second_alpha_field);
    $this->getSession()->getPage()->pressButton('Test rebuild');
    $this->getSession()->getPage()->fillField('Limit', 1);
    $assert_session->checkboxChecked('Entity Test Bundle');
    $assert_session->fieldDisabled($label_first_alpha_field);
    $assert_session->checkboxChecked($label_first_alpha_field);
    $assert_session->fieldDisabled('Entity Test Bundle');
    $assert_session->checkboxChecked($label_second_alpha_field);
    $assert_session->fieldEnabled($label_second_alpha_field);
    $assert_session->checkboxChecked('Beta');
    $assert_session->fieldDisabled('Beta');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Number of values can\'t be smaller than the previously set number: 2.');

    // Tests the deletion form.
    $this->drupalGet('/admin/structure/open-vocabulary-association');
    $assert_session->buttonExists('List additional actions')->press();
    $this->clickLink('Delete');
    $assert_session->pageTextContainsOnce('Are you sure you want to delete the vocabulary association Association 1?');
    $assert_session->linkExists('Cancel');
    $assert_session->buttonExists('Delete')->press();
    $assert_session->pageTextContains('The vocabulary association Association 1 has been deleted.');
  }

}
