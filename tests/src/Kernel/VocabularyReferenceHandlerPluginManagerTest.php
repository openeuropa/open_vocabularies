<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\src\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\open_vocabularies\VocabularyReferenceHandlerDefault;

/**
 * Tests the vocabulary reference handler plugin manager.
 *
 * @coversDefaultClass \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManager
 */
class VocabularyReferenceHandlerPluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'open_vocabularies',
    'open_vocabularies_test',
  ];

  /**
   * Tests the plugin discovery and info alter hook.
   */
  public function testDiscovery(): void {
    $manager = $this->container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler');

    $expected = [
      'test_entity_plugin' => [
        'label' => new TranslatableMarkup('Test entities'),
        'id' => 'test_entity_plugin',
        'target_type' => 'entity_test',
        'class' => VocabularyReferenceHandlerDefault::class,
        'provider' => 'open_vocabularies_test',
      ],
      'test_alter_hook' => [
        'label' => new TranslatableMarkup('Tests the info alter hook'),
        'id' => 'test_alter_hook',
        'target_type' => 'entity_test_label',
        'class' => VocabularyReferenceHandlerDefault::class,
        'provider' => 'open_vocabularies_test',
        'definition_altered' => TRUE,
      ],
      'entity_test_with_bundle' => [
        'label' => new TranslatableMarkup('Entity test with bundle'),
        'id' => 'entity_test_with_bundle',
        'target_type' => 'entity_test_with_bundle',
        'class' => VocabularyReferenceHandlerDefault::class,
        'provider' => 'open_vocabularies_test',
      ],
    ];
    $this->assertEquals($expected, $manager->getDefinitions());
  }

  /**
   * Tests the method to get plugin definitions as options.
   *
   * @covers ::getDefinitionsAsOptions()
   */
  public function testGetDefinitionsAsOptions(): void {
    $manager = $this->container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler');

    $expected = [
      'test_entity_plugin' => new TranslatableMarkup('Test entities'),
      'test_alter_hook' => new TranslatableMarkup('Tests the info alter hook'),
      'entity_test_with_bundle' => new TranslatableMarkup('Entity test with bundle'),
    ];
    $this->assertEquals($expected, $manager->getDefinitionsAsOptions());
  }

}
