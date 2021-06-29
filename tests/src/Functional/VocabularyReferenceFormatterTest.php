<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Functional;

/**
 * Tests the vocabulary reference formatter user interfaces.
 *
 * @group open_vocabularies
 */
class VocabularyReferenceFormatterTest extends OpenVocabularyTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
  ];

  /**
   * Tests the settings form for the label formatter.
   */
  public function testLabelFormatterSettings(): void {
    $this->drupalLogin($this->createUser(['administer node display']));
    $this->drupalGet('/admin/structure/types/manage/page/display');

    $assert_session = $this->assertSession();
    // Place the label formatter in the content region.
    $assert_session->selectExists('fields[vocabulary_reference][region]')->selectOption('content');
    $assert_session->selectExists('fields[vocabulary_reference][type]')->selectOption('open_vocabulary_reference_label');
    $this->getSession()->getPage()->pressButton('Save');

    // Verify the default options.
    $row = $assert_session->elementExists('xpath', '//table/tbody/tr[./td[1][text()="Vocabularies"]]');
    $this->assertStringContainsString('Link to the referenced entity', $row->getText());
    $assert_session->buttonExists('vocabulary_reference_settings_edit', $row)->press();
    $checkbox = $assert_session->fieldExists('Link label to the referenced entity', $row);
    $this->assertTrue($checkbox->isChecked());

    // Disable the link option and verify the summary.
    $checkbox->uncheck();
    $assert_session->buttonExists('Update', $row)->press();
    $this->assertStringNotContainsString('Link to the referenced entity', $row->getText());
    $this->assertStringContainsString('No link', $row->getText());
    // Verify that current value is correctly set as default in the settings
    // form.
    $assert_session->buttonExists('vocabulary_reference_settings_edit', $row)->press();
    $assert_session->checkboxNotChecked('Link label to the referenced entity', $row);
  }

}
