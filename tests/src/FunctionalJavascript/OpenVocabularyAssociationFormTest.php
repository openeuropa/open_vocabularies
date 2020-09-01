<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\Tests\open_vocabularies\Traits\NativeBrowserValidationTrait;
use Drupal\Tests\open_vocabularies\Traits\VocabularyCreationTrait;

/**
 * Tests the open vocabulary association entity forms.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAssociationFormTest extends OpenVocabulariesFormTestBase {

  use NativeBrowserValidationTrait;
  use VocabularyCreationTrait;

  /**
   * The field instance labels created for this test, keyed by field name.
   *
   * @var array
   */
  protected $fieldInstances = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create three vocabulary reference field instances. Prefix them with
    // defined characters so we can expect the order.
    foreach (['a', 'b', 'c'] as $prefix) {
      $storage = FieldStorageConfig::create([
        'field_name' => $prefix . strtolower($this->randomMachineName()),
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
      $this->fieldInstances[$instance->id()] = $instance->label();
    }

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
    $assert_session->pageTextContains('Fields field is required.');
    $assert_session->pageTextContains('Widget type field is required.');
    $assert_session->pageTextContains('Vocabulary field is required.');
    $assert_session->pageTextContains('Predicate field is required.');

    // Verify that the correct fields options are listed.
    $expected_options = array_map(function (string $label): string {
      return sprintf('Field %s on entity entity_test, bundle entity_test', $label);
    }, $this->fieldInstances);
    $this->assertEquals($expected_options, $this->getOptions('Fields'));
    // Select two out of the three options.
    $options_selected = array_keys($this->fieldInstances);
    unset($options_selected[2]);
    $this->getSession()->getPage()->selectFieldOption('Fields', $options_selected[0]);
    $this->getSession()->getPage()->selectFieldOption('Fields', $options_selected[1], TRUE);

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
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Created new vocabulary association Association 1.');

    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = \Drupal::entityTypeManager()->getStorage('open_vocabulary_association')->load($vocabulary->id() . '.association_1');
    $this->assertInstanceOf(OpenVocabularyAssociationInterface::class, $association);
    $this->assertEquals('association_1', $association->getName());
    $this->assertEquals('Association 1', $association->label());
    $this->assertEquals($options_selected, $association->getFields());
    $this->assertEquals('options_select', $association->getWidgetType());
    $this->assertEquals($vocabulary->id(), $association->getVocabulary());
    $this->assertEquals(1, $association->getCardinality());
    // @todo update the predicate.
    $this->assertEquals('http://example.com/#contain', $association->getPredicate());
    $this->assertEquals('A description to help.', $association->getHelpText());
    $this->assertTrue($association->isRequired());

    // Edit the vocabulary association.
    $this->clickLink('Edit');
    $assert_session->fieldDisabled('Fields');
    $assert_session->fieldDisabled('Vocabulary');
    $assert_session->fieldDisabled('Allowed number of values');
    $assert_session->fieldDisabled('Limit');
    $assert_session->fieldValueEquals('Label', 'Association 1');
    $this->assertEquals($options_selected, $assert_session->selectExists('Fields')->getValue());
    $assert_session->fieldValueEquals('Widget type', 'options_select');
    $assert_session->fieldValueEquals('Vocabulary', $vocabulary->id());
    // @todo update the predicate.
    $assert_session->fieldValueEquals('Predicate', 'http://example.com/#contain');
    $assert_session->fieldValueEquals('Help text', 'A description to help.');
    $assert_session->checkboxChecked('Required');
    // Change the help text and submit the form.
    $this->getSession()->getPage()->fillField('Help text', 'A description to help the users.');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Vocabulary association Association 1.');

    // Tests the deletion form.
    $assert_session->buttonExists('List additional actions')->press();
    $this->clickLink('Delete');
    $assert_session->pageTextContainsOnce('Are you sure you want to delete the vocabulary association Association 1?');
    $assert_session->linkExists('Cancel');
    $assert_session->buttonExists('Delete')->press();
    $assert_session->pageTextContains('The vocabulary association Association 1 has been deleted.');
  }

}
