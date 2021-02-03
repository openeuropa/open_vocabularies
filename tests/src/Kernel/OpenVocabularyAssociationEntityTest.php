<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyCreationTrait;

/**
 * Tests the OpenVocabularyAssociation entity.
 *
 * @group open_vocabularies
 */
class OpenVocabularyAssociationEntityTest extends KernelTestBase {

  use VocabularyCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'open_vocabularies',
    'open_vocabularies_test',
  ];

  /**
   * Tests the entity class methods.
   */
  public function testEntityClass(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('open_vocabulary_association');

    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = $storage->create();
    $this->assertNull($association->label());
    $this->assertEquals('.', $association->id());
    $this->assertNull($association->getWidgetType());
    $this->assertFalse($association->isRequired());
    $this->assertNull($association->getHelpText());
    $this->assertNull($association->getPredicate());
    $this->assertEquals(1, $association->getCardinality());

    $association->set('cardinality', '3');
    $this->assertSame(3, $association->getCardinality());
    $association->set('required', '1');
    $this->assertTrue($association->isRequired());

    $vocabulary = $this->createVocabulary();
    $values = [
      'label' => $this->randomString(),
      'vocabulary' => $vocabulary->id(),
      'name' => strtolower($this->randomMachineName()),
      'widget_type' => 'options_select',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
    ];
    $storage->create($values)->save();

    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = $storage->load($vocabulary->id() . '.' . $values['name']);
    $this->assertEquals($values['label'], $association->label());
    $this->assertEquals($values['vocabulary'], $vocabulary->id());
    $this->assertEquals($values['widget_type'], $association->getWidgetType());
    $this->assertEquals($values['required'], $association->isRequired());
    $this->assertEquals($values['help_text'], $association->getHelpText());
    $this->assertEquals($values['predicate'], $association->getPredicate());
    $this->assertEquals($values['cardinality'], $association->getCardinality());
  }

}
