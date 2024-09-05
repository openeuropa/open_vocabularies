<?php

declare(strict_types=1);

namespace Drupal\Tests\open_vocabularies\Functional;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Tests the administration side of open vocabulary association entities.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAssociationAdminTest extends BrowserTestBase {

  use VocabularyTestTrait;

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
   * Tests the vocabulary association route access.
   */
  public function testVocabularyAssociationRouteAccess(): void {
    $vocabulary = $this->createVocabulary();
    $association = $this->createVocabularyAssociation($vocabulary->id());

    $urls = [
      $association->toUrl('collection'),
      $association->toUrl('add-form'),
      $association->toUrl('edit-form'),
      $association->toUrl('delete-form'),
    ];

    foreach ($urls as $url) {
      $this->drupalGet($url);
      $this->assertSession()->statusCodeEquals(403);
    }

    // Log in as a user with the administration permission.
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabulary associations',
    ]));
    foreach ($urls as $url) {
      $this->drupalGet($url);
      $this->assertSession()->statusCodeEquals(200);
    }
  }

  /**
   * Tests the vocabulary association list builder table.
   */
  public function testVocabularyAssociationListBuilder(): void {
    $storage = FieldStorageConfig::create([
      'field_name' => 'vocabulary_reference',
      'entity_type' => 'entity_test',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
      'label' => 'Vocabularies',
    ])->save();
    EntityTestBundle::create(['id' => 'test_a', 'label' => 'Test bundle A'])->save();
    EntityTestBundle::create(['id' => 'test_b', 'label' => 'Test bundle B'])->save();
    $storage = FieldStorageConfig::create([
      'field_name' => 'vocabulary_reference',
      'entity_type' => 'entity_test_with_bundle',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'test_a',
      'label' => 'Vocabularies',
    ])->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'test_b',
      'label' => 'Vocabularies',
    ])->save();

    // Log in as a user with the administration permission.
    $this->drupalLogin($this->drupalCreateUser([
      'administer open vocabulary associations',
    ]));

    $this->drupalGet('/admin/structure/open-vocabulary-association');
    $this->assertSession()->pageTextContainsOnce('There are no vocabulary associations yet.');

    $table = $this->assertSession()->elementExists('xpath', '//div[@class="layout-content"]//table');
    $headers = $table->findAll('xpath', '/thead/tr/th');
    $this->assertCount(7, $headers);

    $expected_headers = [
      'Label',
      'Machine name',
      'Widget type',
      'Vocabulary',
      'Entity types',
      'Weight',
      'Operations',
    ];
    foreach ($headers as $key => $header) {
      $this->assertEquals($expected_headers[$key], $header->getText());
    }

    // Create a vocabulary.
    $vocabulary = $this->createVocabulary([
      'label' => $this->randomMachineName(),
    ]);

    // Create 2 vocabulary associations.
    $associations = [];
    $values = [
      'label' => 'Association 0',
      'vocabulary' => $vocabulary->id(),
      'name' => 'association_0',
      'widget_type' => 'options_select',
      'fields' => [
        'entity_test.entity_test.open_vocabulary_reference',
      ],
    ];
    $association = OpenVocabularyAssociation::create($values);
    $association->save();
    $associations[] = $association;

    $values['label'] = 'Association 1';
    $values['name'] = 'association_1';
    $values['fields'] = [
      'entity_test_with_bundle.test_a.open_vocabulary_reference',
      'entity_test_with_bundle.test_b.open_vocabulary_reference',
    ];
    $association = OpenVocabularyAssociation::create($values);
    $association->save();
    $associations[] = $association;

    $this->drupalGet('/admin/structure/open-vocabulary-association');
    $this->assertSession()->pageTextNotContains('There are no vocabulary associations yet.');
    $rows = $this->getSession()->getPage()->findAll('xpath', '//div[@class="layout-content"]//table/tbody/tr');
    $this->assertCount(2, $rows);

    // Verify that the cells contain the correct data.
    foreach (['Association 0', 'Association 1'] as $row => $label) {
      $cells = $rows[$row]->findAll('xpath', '/td');
      $this->assertEquals($label, $cells[0]->getText());
      $this->assertEquals('association_' . $row, $cells[1]->getText());
      $this->assertEquals('Select list', $cells[2]->getText());
      $this->assertEquals($vocabulary->label(), $cells[3]->getText());
      if ($row === 0) {
        $this->assertEquals('Test entity', $cells[4]->getText());
      }
      if ($row === 1) {
        $this->assertEquals('Test entity with bundle: Test bundle A, Test bundle B', $cells[4]->getText());
      }
      // Assert the weight value which in the beginning is the same as the row
      // number.
      $this->assertEquals($row, $this->getSession()->getPage()->findField('entities[' . $associations[$row]->id() . '][weight]')->getValue());
      $this->assertSession()->elementExists('xpath', '//a[starts-with(@href, "' . $associations[$row]->toUrl('edit-form')->toString() . '")]', $cells[6]);
      $this->assertSession()->elementExists('xpath', '//a[starts-with(@href, "' . $associations[$row]->toUrl('delete-form')->toString() . '")]', $cells[6]);
    }

    // Re-order the associations and assert the order has changed.
    $this->getSession()->getPage()->fillField('entities[' . $associations[0]->id() . '][weight]', 10);
    $this->getSession()->getPage()->fillField('entities[' . $associations[1]->id() . '][weight]', 5);
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('The order of the vocabulary associations has been updated.');
    $this->assertEquals(10, $this->getSession()->getPage()->findField('entities[' . $associations[0]->id() . '][weight]')->getValue());
    $this->assertEquals(5, $this->getSession()->getPage()->findField('entities[' . $associations[1]->id() . '][weight]')->getValue());
    foreach (['Association 1', 'Association 0'] as $row => $label) {
      $cells = $rows[$row]->findAll('xpath', '/td');
      $this->assertEquals($label, $cells[0]->getText());
    }
  }

}
