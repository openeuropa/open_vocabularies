<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\FunctionalJavascript;

use Drupal\Tests\open_vocabularies\Traits\VocabularyCreationTrait;

/**
 * Tests the open vocabulary entity forms.
 *
 * @group open_vocabularies
 */
class OpenVocabularyFormTest extends OpenVocabulariesFormTestBase {

  use VocabularyCreationTrait;

  /**
   * Tests the create and update routes.
   */
  public function testVocabularyCreationUpdate(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabularies',
      'access content',
    ]));
    $this->drupalGet('/admin/structure/open-vocabulary');
    $this->clickLink('Add vocabulary');

    $assert_session = $this->assertSession();
    $assert_session->fieldExists('Label')->setValue('Vocabulary 1');
    // Wait for the machine name to appear.
    $assert_session->waitForText('Machine name: vocabulary_1');

    $description = $this->randomString(20);
    $assert_session->fieldExists('Description')->setValue($description);
    $assert_session->elementExists('named', ['fieldset', 'Vocabulary type']);
    $assert_session->elementExists('named', ['radio', 'Test entities']);
    $assert_session->elementExists('named', [
      'radio',
      'Tests the info alter hook',
    ]);
    $assert_session->elementExists('named', ['radio', 'Entity test with bundle']);
    // Verify that the vocabulary type field is required.
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Error message Vocabulary type field is required.');

    // Choose a vocabulary type.
    $radio = $this->getSession()->getPage()->find('named', [
      'radio',
      'Tests the info alter hook',
    ]);
    $radio->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementExists('named', [
      'fieldset',
      'Entity Test label bundle',
    ]);
    $assert_session->checkboxNotChecked('Entity Test Bundle');
    $assert_session->pageTextNotContains('Please note: this vocabulary is used by one or more associations');

    // Change the selected vocabulary type.
    $radio = $this->getSession()->getPage()->find('named', [
      'radio',
      'Entity test with bundle',
    ]);
    $radio->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains('Please note: this vocabulary is used by one or more associations');
    // Verify that the previous vocabulary reference handler form elements have
    // been replaced with the ones of the newly selected handler.
    $assert_session->elementNotExists('named', [
      'fieldset',
      'Entity Test label bundle',
    ]);
    $assert_session->elementNotExists('named', [
      'checkbox',
      'Entity Test Bundle',
    ]);
    $assert_session->elementExists('named', ['fieldset', 'Test entity bundle']);
    $assert_session->checkboxNotChecked('Alpha');
    $assert_session->checkboxNotChecked('Beta');
    // Verify that at least a bundle must be specified.
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Error message Test entity bundle field is required.');

    // Verify that the entity reference plugin ajax and states functionalities
    // work correctly.
    $sorty_by = $assert_session->fieldExists('Sort by');
    // @todo Restore this line when Drupal 8.8 compatibility is dropped.
    // $this->assertFalse($sorty_by->isVisible());
    $this->getSession()->getPage()->findField('Alpha')->check();
    $assert_session->assertWaitOnAjaxRequest();
    $sort_by = $assert_session->fieldExists('Sort by');
    $this->assertTrue($sort_by->isVisible());
    $sort_by->selectOption('id');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldExists('Sort direction')->selectOption('DESC');

    // Verify that the auto create field is not rendered.
    $assert_session->fieldNotExists("Create referenced entities if they don't already exist");

    // Submit the form.
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Status message Created new vocabulary Vocabulary 1.');

    // Load the vocabulary config entity and assert that all values have been
    // saved correctly.
    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $vocabulary */
    $vocabulary = \Drupal::entityTypeManager()->getStorage('open_vocabulary')->load('vocabulary_1');
    $this->assertEquals([
      'target_bundles' => ['alpha' => 'alpha'],
      'sort' => [
        'field' => 'id',
        'direction' => 'DESC',
      ],
      'auto_create' => FALSE,
      'auto_create_bundle' => NULL,
    ], $vocabulary->getHandlerSettings());

    // Edit the entity again.
    $this->clickLink('Edit');
    // The ID cannot be changed anymore.
    $assert_session->fieldDisabled('id');
    // Verify that the entered values are presented in the form.
    $assert_session->fieldValueEquals('Label', 'Vocabulary 1');
    $assert_session->fieldValueEquals('Description', $description);
    $this->assertTrue($assert_session->elementExists('named', [
      'radio',
      'Entity test with bundle',
    ])->isChecked());
    $assert_session->pageTextNotContains('Please note: this vocabulary is used by one or more associations');
    $assert_session->checkboxChecked('Alpha');
    $assert_session->checkboxNotChecked('Beta');
    $assert_session->fieldValueEquals('Sort by', 'id');
    $assert_session->fieldValueEquals('Sort direction', 'DESC');

    // Check another bundle and verify that the auto create bundle field does
    // not appear.
    $this->getSession()->getPage()->findField('Beta')->check();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldNotExists('Store new items in');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('Status message Updated vocabulary Vocabulary 1.');

    // Create an association that targets this vocabulary.
    $this->createVocabularyAssociation('vocabulary_1');

    // Edit the vocabulary once more and check that the vocabulary type cannot
    // be changed anymore and a message is present in the page.
    $this->clickLink('Edit');
    $assert_session->fieldDisabled('Test entities');
    $assert_session->fieldDisabled('Tests the info alter hook');
    $assert_session->fieldDisabled('Entity test with bundle');
    $assert_session->pageTextContains('Please note: this vocabulary is used by one or more associations');
  }

}
