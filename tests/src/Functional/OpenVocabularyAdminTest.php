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

}
