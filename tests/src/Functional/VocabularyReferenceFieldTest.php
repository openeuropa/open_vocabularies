<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Functional;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyCreationTrait;

/**
 * Tests the UI facing functionalities.
 */
class VocabularyReferenceFieldTest extends BrowserTestBase {

  use VocabularyCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'entity_test',
    'field_ui',
    'node',
    'open_vocabularies',
    'open_vocabularies_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('system_messages_block');
    $this->drupalPlaceBlock('page_title_block');

    NodeType::create([
      'type' => 'page',
    ])->save();

    $storage = FieldStorageConfig::create([
      'field_name' => 'vocabulary_reference',
      'entity_type' => 'node',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'page',
      'label' => 'Vocabularies',
    ])->save();

    // Create a field of another type to test the placement in the form.
    $storage = FieldStorageConfig::create([
      'field_name' => 'responsible',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'user',
      ],
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'page',
      'label' => 'Responsible person',
    ])->save();

    // Create bundles that will be used as referenced bundles.
    EntityTestBundle::create(['id' => 'country'])->save();

    // Create a vocabulary with two associations.
    $this->createVocabulary([
      'id' => 'countries',
      'handler' => 'entity_test_with_bundle',
      'handler_settings' => [
        'target_bundles' => [
          'country' => 'country',
        ],
      ],
    ]);
    $this->createVocabularyAssociation('countries', [
      'label' => 'Participating countries',
      'name' => 'participating',
      'widget_type' => 'entity_reference_autocomplete',
      'cardinality' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED,
      'fields' => [
        'node.page.vocabulary_reference',
      ],
    ]);
    $this->createVocabularyAssociation('countries', [
      'label' => 'Hosting country',
      'name' => 'hosting',
      'widget_type' => 'options_select',
      'cardinality' => 1,
      'fields' => [
        'node.page.vocabulary_reference',
      ],
    ]);

    // Create a few countries.
    foreach (['Italy', 'Belgium', 'Lithuania', 'Germany'] as $name) {
      EntityTestWithBundle::create([
        'type' => 'country',
        'name' => $name,
      ])->save();
    }
  }

  /**
   * Tests the user facing parts.
   */
  public function testUi(): void {
    $this->drupalLogin($this->createUser(['administer node form display']));
    $this->drupalGet('/admin/structure/types/manage/page/form-display');

    $assert_session = $this->assertSession();
    // Place the vocabulary reference placeholder widget after the title and
    // the extra field after the reference.
    $assert_session->selectExists('fields[vocabulary_reference][region]')->selectOption('content');
    $assert_session->selectExists('fields[responsible][region]')->selectOption('content');
    $assert_session->fieldExists('fields[vocabulary_reference][weight]')->setValue(-4);
    $assert_session->fieldExists('fields[responsible][weight]')->setValue(-3);
    // Make sure that a consistent widget is used.
    $assert_session->fieldExists('fields[responsible][type]')->setValue('options_select');
    $this->getSession()->getPage()->pressButton('Save');

    $row = $assert_session->elementExists('xpath', '//table/tbody/tr[./td[1][text()="Vocabularies"]]');
    $this->assertContains('This widget does not render any form element. It can be used as placeholder to position the single reference fields on the entity form display.', $row->getText());

    $this->drupalLogin($this->createUser([
      'access content',
      'create page content',
      'edit any page content',
      'view test entity',
    ]));
    $this->drupalGet('/node/add/page');

    // Prepare an XPath to assert the position of the elements.
    // Find the node form first.
    $xpath = '(((//form[@data-drupal-selector="node-page-form"]'
      // Find the title field wrapper.
      . '/div[@class and contains(concat(" ", normalize-space(@class), " "), " field--type-string ")]'
      . '[.//label[text()="Title"]]'
      // Take the first following sibling of the title element.
      . '/following-sibling::div)[1]'
      // It should be an entity reference.
      . '[@class and contains(concat(" ", normalize-space(@class), " "), " field--type-entity-reference ")]'
      // Make sure that the element is the participating countries wrapper.
      . '[.//table//th/h4[text()="Participating countries"]]'
      // Again take the first sibling.
      . '/following-sibling::div)[1]'
      // It should be an entity reference.
      . '[@class and contains(concat(" ", normalize-space(@class), " "), " field--type-entity-reference ")]'
      // And it should contain the hosting country field.
      . '[.//label[text()="Hosting country"]]'
      // The next sibling is the wrapper of the responsible person field.
      . '/following-sibling::div)[1]'
      . '[@class and contains(concat(" ", normalize-space(@class), " "), " field--type-entity-reference ")]'
      . '[.//label[text()="Responsible person"]]';
    $assert_session->elementExists('xpath', $xpath);

    $this->assertEquals([
      '- Select a value -',
      'Italy',
      'Belgium',
      'Lithuania',
      'Germany',
    ], array_values($this->getOptions('Hosting country')));
    $assert_session->selectExists('Hosting country')->selectOption('Lithuania');

    // Add values in order to click trigger at least two AJAX requests.
    // This covers a bug occurring after the form display is hydrated from the
    // form state cache. The first form rebuild request has no cache set, so
    // we need the second one to replicate the bug.
    // @see \Drupal\open_vocabularies\VocabularyReferenceFieldsManager::entityFormDisplayAlter()
    $assert_session->fieldExists('Participating countries (value 1)')->setValue('Belgium');
    $assert_session->buttonExists('Add another item')->press();
    $assert_session->fieldExists('Participating countries (value 2)')->setValue('Italy');
    $assert_session->buttonExists('Add another item')->press();
    $assert_session->fieldExists('Participating countries (value 3)')->setValue('Lithuania');

    $assert_session->fieldExists('Title')->setValue('Test page');
    $assert_session->buttonExists('Save')->press();
    $assert_session->pageTextContains('Test page has been created.');

    $this->drupalGet('/node/1/edit');
    // Check that values are re-populated properly.
    $this->assertTrue($assert_session->optionExists('Hosting country', 'Lithuania')->isSelected());
    $this->assertStringStartsWith('Belgium', $assert_session->fieldExists('Participating countries (value 1)')->getValue());
    $this->assertStringStartsWith('Italy', $assert_session->fieldExists('Participating countries (value 2)')->getValue());
    $this->assertStringStartsWith('Lithuania', $assert_session->fieldExists('Participating countries (value 3)')->getValue());
  }

}
