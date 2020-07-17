<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\src\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\open_vocabularies\OpenVocabularyInterface;

/**
 * Tests the OpenVocabulary entity.
 *
 * @group open_vocabularies
 */
class OpenVocabularyEntityTest extends KernelTestBase {

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
    $storage->create($values)->save();
    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $vocabulary */
    $vocabulary = $storage->load($values['id']);
    $this->assertInstanceOf(OpenVocabularyInterface::class, $vocabulary);
    $this->assertEquals($values['label'], $vocabulary->label());
    $this->assertEquals($values['description'], $vocabulary->getDescription());
    $this->assertEquals($values['handler'], $vocabulary->getHandler());
    $this->assertEquals($values['handler_settings'], $vocabulary->getHandlerSettings());
  }

}
