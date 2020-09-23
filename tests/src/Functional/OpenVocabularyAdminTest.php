<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyCreationTrait;

/**
 * Tests the administration functionality regarding open vocabulary entities.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAdminTest extends BrowserTestBase {

  use VocabularyCreationTrait;

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
  protected $defaultTheme = 'stark';

  /**
   * Tests the vocabulary route access.
   */
  public function testVocabularyRouteAccess(): void {
    $vocabulary = $this->createVocabulary();
    $urls = [
      $vocabulary->toUrl('collection'),
      $vocabulary->toUrl('add-form'),
      $vocabulary->toUrl('edit-form'),
      $vocabulary->toUrl('delete-form'),
    ];

    foreach ($urls as $url) {
      $this->drupalGet($url);
      $this->assertSession()->statusCodeEquals(403);
    }

    // Log in as a user with the administration permission.
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabularies',
    ]));
    foreach ($urls as $url) {
      $this->drupalGet($url);
      $this->assertSession()->statusCodeEquals(200);
    }
  }

  /**
   * Tests the vocabulary list builder table.
   */
  public function testVocabularyListBuilder(): void {
    // Log in as a user with the administration permission.
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabularies',
    ]));

    $this->drupalGet('/admin/structure/open-vocabulary');
    $this->assertSession()->pageTextContainsOnce('There are no vocabularies yet.');

    $table = $this->assertSession()->elementExists('xpath', '//div[@class="layout-content"]//table');
    $headers = $table->findAll('xpath', '/thead/tr/th');
    $this->assertCount(5, $headers);

    $expected_headers = [
      'Label',
      'Machine name',
      'Description',
      'Type',
      'Operations',
    ];
    foreach ($headers as $key => $header) {
      $this->assertEquals($expected_headers[$key], $header->getText());
    }

    // Create a vocabulary.
    $vocabulary = $this->createVocabulary();

    $this->drupalGet('/admin/structure/open-vocabulary');
    $this->assertSession()->pageTextNotContains('There are no vocabularies yet.');
    $rows = $this->getSession()->getPage()->findAll('xpath', '//div[@class="layout-content"]//table/tbody/tr');
    $this->assertCount(1, $rows);

    // Verify that the cells contain the correct data.
    $cells = $rows[0]->findAll('xpath', '/td');
    $this->assertEquals($vocabulary->label(), $cells[0]->getText());
    $this->assertEquals($vocabulary->id(), $cells[1]->getText());
    $this->assertEquals($vocabulary->getDescription(), $cells[2]->getText());
    $this->assertEquals('Test entities', $cells[3]->getText());
    $this->assertSession()->elementExists('xpath', '//a[starts-with(@href, "' . $vocabulary->toUrl('edit-form')->toString() . '")]', $cells[4]);
    $this->assertSession()->elementExists('xpath', '//a[starts-with(@href, "' . $vocabulary->toUrl('delete-form')->toString() . '")]', $cells[4]);
  }

  /**
   * Tests the delete form.
   */
  public function testForm(): void {
    $countries = $this->createVocabulary([
      'label' => 'Countries',
    ]);
    $languages = $this->createVocabulary([
      'label' => 'Languages',
    ]);

    // Create some associations for the languages vocabulary.
    $association_one = $this->createVocabularyAssociation($languages->id(), [
      'label' => 'Translations',
      'name' => 'document_translations',
    ]);
    $association_two = $this->createVocabularyAssociation($languages->id(), [
      'label' => 'Original language',
      'name' => 'orig_language',
    ]);

    // Log in as a user with the administration permission.
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabularies',
    ]));

    // The country vocabulary has no associations so the normal form behaviour
    // will be applied.
    $assert_session = $this->assertSession();
    $this->drupalGet($countries->toUrl('delete-form'));
    $assert_session->titleEquals('Are you sure you want to delete the vocabulary Countries? | Drupal');
    $assert_session->pageTextContains('Are you sure you want to delete the vocabulary Countries?');
    $assert_session->pageTextContains('This action cannot be undone.');
    $assert_session->linkExists('Cancel');
    $assert_session->buttonExists('Delete')->press();
    $assert_session->pageTextContains('The vocabulary Countries has been deleted.');

    // The languages vocabulary has two associations so the deletion is
    // prevented.
    $this->drupalGet($languages->toUrl('delete-form'));
    $assert_session->titleEquals('Cannot delete the vocabulary Languages | Drupal');
    $assert_session->pageTextContains('Languages vocabulary is used by one or more associations and it cannot be deleted.');
    $assert_session->pageTextContains('Associations referencing this vocabulary:');
    $assert_session->pageTextContains('Translations (document_translations)');
    $assert_session->pageTextContains('Original language (orig_language)');
    $assert_session->buttonNotExists('Delete');
    $assert_session->linkExists('Cancel');
    $this->getSession()->getPage()->clickLink('Cancel');
    $this->assertUrl('admin/structure/open-vocabulary');

    // Delete the associations and try again to delete the form.
    $association_one->delete();
    $association_two->delete();
    $this->drupalGet($languages->toUrl('delete-form'));
    $assert_session->pageTextContains('Are you sure you want to delete the vocabulary Languages?');
    $assert_session->buttonExists('Delete')->press();
    $assert_session->pageTextContains('The vocabulary Languages has been deleted.');
  }

}
