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
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;

/**
 * Base class for browser tests that need a set of pre-created data.
 *
 * @ingroup open_vocabularies
 */
abstract class OpenVocabularyTestBase extends BrowserTestBase {

  use VocabularyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'entity_test',
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
  public function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('system_messages_block');
    $this->drupalPlaceBlock('page_title_block');

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
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
    EntityTestBundle::create([
      'id' => 'country',
      'label' => 'Country',
    ])->save();

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

}
