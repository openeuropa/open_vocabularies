<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\FunctionalJavascript;

use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;

/**
 * Tests the open vocabulary association entity forms.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAssociationFormTest extends OpenVocabulariesFormTestBase {

  /**
   * Tests the create, update and delete routes.
   */
  public function testVocabularyAssociationCreationUpdateDeletion(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabularies',
      'administer open vocabulary associations',
      'access content',
    ]));

    // Create a vocabulary.
    $this->drupalGet('/admin/structure/open-vocabulary');
    $this->clickLink('Add vocabulary');

    $assert_session = $this->assertSession();
    $assert_session->fieldExists('Label')->setValue('Vocabulary 1');
    // Wait for the machine name to appear.
    $assert_session->waitForText('Machine name: vocabulary_1');

    $description = $this->randomString(20);
    $assert_session->fieldExists('Description')->setValue($description);
    // Choose a vocabulary type.
    $radio = $this->getSession()->getPage()->find('named', ['radio', 'Test entities']);
    $radio->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Change the selected vocabulary type.
    $this->getSession()->getPage()->checkField('Entity Test Bundle');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Status message Created new vocabulary Vocabulary 1.');

    // Create a vocabulary association.
    $this->drupalGet('/admin/structure/open-vocabulary-association');
    $this->clickLink('Add vocabulary association');

    $assert_session = $this->assertSession();
    $assert_session->fieldExists('Label')->setValue('Vocabulary association 1');
    // Wait for the machine name to appear.
    $assert_session->waitForText('Machine name: vocabulary_association_1');

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
      'Vocabulary 1',
    ]);
    $this->getSession()->getPage()->selectFieldOption('Vocabulary', 'Vocabulary 1');
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
    $assert_session->pageTextContains('Created new vocabulary association Vocabulary association 1.');

    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = \Drupal::entityTypeManager()->getStorage('open_vocabulary_association')->load('vocabulary_1.vocabulary_association_1');
    $this->assertInstanceOf(OpenVocabularyAssociationInterface::class, $association);
    $this->assertEquals('vocabulary_association_1', $association->getName());
    $this->assertEquals('Vocabulary association 1', $association->label());
    // @todo change after using widget.
    $this->assertEquals('test_entity_plugin', $association->getWidgetType());
    $this->assertEquals('vocabulary_1', $association->getVocabulary());
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
    $assert_session->fieldValueEquals('Label', 'Vocabulary association 1');
    // @todo change after using widget.
    $assert_session->fieldValueEquals('Widget type', 'test_entity_plugin');
    $assert_session->fieldValueEquals('Vocabulary', 'vocabulary_1');
    // @todo update the predicate.
    $assert_session->fieldValueEquals('Predicate', 'http://example.com/#contain');
    $assert_session->fieldValueEquals('Help text', 'A description to help.');
    $assert_session->checkboxChecked('Required');
  }

}
