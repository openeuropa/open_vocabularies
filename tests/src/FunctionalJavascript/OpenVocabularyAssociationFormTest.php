<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\FunctionalJavascript;

use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\Tests\open_vocabularies\Traits\NativeBrowserValidationTrait;

/**
 * Tests the open vocabulary association entity forms.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAssociationFormTest extends OpenVocabulariesFormTestBase {

  use NativeBrowserValidationTrait;

  /**
   * Tests the create, update and delete routes.
   */
  public function testVocabularyAssociationCreationUpdateDeletion(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabulary associations',
      'access content',
    ]));

    // Create a vocabulary.
    $values = [
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'handler' => 'test_entity_plugin',
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'entity_test',
        ],
      ],
    ];
    $vocabulary = OpenVocabulary::create($values);
    $vocabulary->save();

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
    $assert_session->pageTextContains('Widget type field is required.');
    $assert_session->pageTextContains('Vocabulary field is required.');
    $assert_session->pageTextContains('Predicate field is required.');

    // @todo add test once we have field values.
    $assert_session->fieldExists('Fields');
    // @todo change after using widget.
    $this->assertFieldSelectOptions('Widget type', [
      '- Select -',
      'Test entities',
      'Tests the info alter hook',
      'Entity test with bundle',
    ]);
    $this->getSession()->getPage()->selectFieldOption('Widget type', 'Test entities');
    $this->assertFieldSelectOptions('Vocabulary', [
      '- Select -',
      $vocabulary->label(),
    ]);
    $this->getSession()->getPage()->selectFieldOption('Vocabulary', $vocabulary->label());
    $this->assertFieldSelectOptions('Predicate', [
      '- Select -',
      'Contain',
      'About',
    ]);
    $this->getSession()->getPage()->selectFieldOption('Predicate', 'Contain');
    $this->assertFieldSelectOptions('Allowed number of values', [
      'Limited',
      'Unlimited',
    ]);
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
    // @todo change after using widget.
    $this->assertEquals('test_entity_plugin', $association->getWidgetType());
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
    // @todo change after using widget.
    $assert_session->fieldValueEquals('Widget type', 'test_entity_plugin');
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
