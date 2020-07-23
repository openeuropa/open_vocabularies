<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\src\Functional;

use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the administration functionalities regarding open vocabulary entities.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAdminTest extends BrowserTestBase {

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
   * Tests the route access.
   */
  public function testRouteAccess(): void {
    $vocabulary = OpenVocabulary::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'handler' => 'test_entity_plugin',
    ]);
    $vocabulary->save();

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
   * Tests the list builder table.
   */
  public function testListBuilder(): void {
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
    $values = [
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'description' => $this->randomString(128),
      'handler' => 'test_entity_plugin',
    ];
    $vocabulary = OpenVocabulary::create($values);
    $vocabulary->save();

    $this->drupalGet('/admin/structure/open-vocabulary');
    $this->assertSession()->pageTextNotContains('There are no vocabularies yet.');
    $rows = $this->getSession()->getPage()->findAll('xpath', '//div[@class="layout-content"]//table/tbody/tr');
    $this->assertCount(1, $rows);

    // Verify that the cells contain the correct data.
    $cells = $rows[0]->findAll('xpath', '/td');
    $this->assertEquals($values['label'], $cells[0]->getText());
    $this->assertEquals($values['id'], $cells[1]->getText());
    $this->assertEquals($values['description'], $cells[2]->getText());
    $this->assertEquals('Test entities', $cells[3]->getText());
    $this->assertSession()->elementExists('xpath', '//a[starts-with(@href, "' . $vocabulary->toUrl('edit-form')->toString() . '")]', $cells[4]);
    $this->assertSession()->elementExists('xpath', '//a[starts-with(@href, "' . $vocabulary->toUrl('delete-form')->toString() . '")]', $cells[4]);
  }

}
