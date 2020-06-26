<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;

/**
 * Defines a plugin manager to deal with vocabulary_reference_handlers.
 *
 * Modules can define vocabulary_reference_handlers in a
 * MODULE_NAME.vocabulary_reference_handlers.yml file contained in the module's
 * base directory.
 * Each vocabulary_reference_handler has the following structure:
 *
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     entity_type: STRING
 * @endcode
 */
class VocabularyReferenceHandlerPluginManager extends DefaultPluginManager implements VocabularyReferenceHandlerPluginManagerInterface {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    // The plugin id. Set by the plugin system based on the top-level YAML key.
    'id' => '',
    // The plugin label.
    'label' => '',
    // The entity type this handler can reference.
    'entity_type' => '',
    // Default plugin class.
    'class' => 'Drupal\open_vocabularies\VocabularyReferenceHandlerDefault',
  ];

  /**
   * Creates a new VocabularyReferenceHandlerPluginManager instance.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->factory = new ContainerFactory($this, '\Drupal\open_vocabularies\VocabularyReferenceHandlerInterface');
    $this->moduleHandler = $module_handler;
    $this->alterInfo('vocabulary_reference_handler_info');
    $this->setCacheBackend($cache_backend, 'vocabulary_reference_handler_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('vocabulary_reference_handlers', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsAsOptions(): array {
    $options = [];

    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }

    return $options;
  }

}
