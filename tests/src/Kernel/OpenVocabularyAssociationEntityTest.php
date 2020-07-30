<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\src\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the OpenVocabularyAssociation entity.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAssociationEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'open_vocabularies',
    'open_vocabularies_test',
  ];

  /**
   * A test open vocabulary.
   *
   * @var \Drupal\open_vocabularies\OpenVocabularyInterface
   */
  protected $vocabulary;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $storage = $this->container->get('entity_type.manager')->getStorage('open_vocabulary');

    $values = [
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'description' => $this->randomString(128),
      'handler' => 'test_entity_plugin',
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'entity_test',
        ],
      ],
    ];
    $this->vocabulary = $storage->create($values);
    $this->vocabulary->save();
  }

  /**
   * Tests the entity class methods.
   */
  public function testEntityClass(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('open_vocabulary_association');

    $values = [
      'label' => $this->randomString(),
      'vocabulary' => $this->vocabulary->id(),
      'name' => strtolower($this->randomMachineName()),
      // @todo change after using widget.
      'widget_type' => 'test_entity_plugin',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
    ];
    $storage->create($values)->save();
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = $storage->load($this->vocabulary->id() . '.' . $values['name']);
    $this->assertEquals($values['label'], $association->label());
    $this->assertEquals($values['vocabulary'], $this->vocabulary->id());
    // @todo change after using widget.
    $this->assertEquals($values['widget_type'], $association->getWidgetType());
    $this->assertEquals($values['required'], $association->isRequired());
    $this->assertEquals($values['help_text'], $association->getHelpText());
    $this->assertEquals($values['predicate'], $association->getPredicate());
    $this->assertEquals($values['cardinality'], $association->getCardinality());
  }

}
