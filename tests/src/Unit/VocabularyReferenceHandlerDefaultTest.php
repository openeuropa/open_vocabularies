<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\src\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\open_vocabularies\VocabularyReferenceHandlerDefault;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the vocabulary reference handler default plugin.
 *
 * @coversDefaultClass \Drupal\open_vocabularies\VocabularyReferenceHandlerDefault
 */
class VocabularyReferenceHandlerDefaultTest extends UnitTestCase {

  /**
   * The mocked selection manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $selectionManager;

  /**
   * The mocked string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->selectionManager = $this->createMock('\Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface');
    $this->stringTranslation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
  }

  /**
   * Tests the label() method.
   *
   * @covers ::label()
   */
  public function testLabel(): void {
    $definition['label'] = new TranslatableMarkup('Example plugin label', [], [], $this->stringTranslation);
    $this->stringTranslation->expects($this->once())
      ->method('translateString')
      ->with($definition['label'])
      ->willReturn('Translated plugin label');

    $plugin = $this->getPluginInstance($definition);
    $this->assertEquals('Translated plugin label', $plugin->label());
  }

  /**
   * Tests the getHandler() method without extra configuration passed.
   *
   * @covers ::getHandler()
   */
  public function testGetHandlerWithoutConfiguration(): void {
    $definition['target_type'] = 'entity_test';

    $mocked_selection_handler = $this->createMock('\Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface');
    $this->selectionManager->expects($this->once())
      ->method('getInstance')
      ->with($definition)
      ->willReturn($mocked_selection_handler);

    $plugin = $this->getPluginInstance($definition);
    $this->assertSame($mocked_selection_handler, $plugin->getHandler());
  }

  /**
   * Tests the getHandler() method with configuration passed.
   *
   * @covers ::getHandler()
   */
  public function testGetHandlerWithConfiguration(): void {
    $definition['target_type'] = 'entity_test';

    // Prepare a configuration array. Pass an override of the target type. The
    // default plugin class will discard it and use the definition target
    // type.
    $configuration = [
      'target_type' => 'attempted override',
      'extra_config' => 5,
    ];
    $expected_configuration = [
      'target_type' => $definition['target_type'],
      'extra_config' => 5,
    ];

    $mocked_selection_handler = $this->createMock('\Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface');
    $this->selectionManager->expects($this->once())
      ->method('getInstance')
      ->with($expected_configuration)
      ->willReturn($mocked_selection_handler);

    $plugin = $this->getPluginInstance($definition);
    $this->assertSame($mocked_selection_handler, $plugin->getHandler($configuration));
  }

  /**
   * Returns a new handler default plugin instance.
   *
   * @param array $definition
   *   The plugin definition.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return \Drupal\open_vocabularies\VocabularyReferenceHandlerDefault
   *   A new plugin instance.
   */
  protected function getPluginInstance(array $definition = [], string $plugin_id = 'test_handler_plugin', array $configuration = []): VocabularyReferenceHandlerDefault {
    $definition += ['id' => $plugin_id];
    $plugin = new VocabularyReferenceHandlerDefault($configuration, $plugin_id, $definition, $this->selectionManager);
    $plugin->setStringTranslation($this->stringTranslation);

    return $plugin;
  }

}
