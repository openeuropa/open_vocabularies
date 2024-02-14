<?php

declare(strict_types=1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of vocabularies.
 */
class OpenVocabularyListBuilder extends ConfigEntityListBuilder {

  /**
   * The vocabulary reference handler plugin manager.
   *
   * @var \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface
   */
  protected $handlerManager;

  /**
   * Constructs a new OpenVocabularyListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface $handler_manager
   *   The vocabulary reference handler plugin manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, VocabularyReferenceHandlerPluginManagerInterface $handler_manager) {
    parent::__construct($entity_type, $storage);

    $this->handlerManager = $handler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['description'] = $this->t('Description');
    $header['handler'] = $this->t('Type');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['description'] = $entity->getDescription();

    $definition = $this->handlerManager->getDefinition($entity->getHandler());
    $row['handler'] = $definition['label'];

    return $row + parent::buildRow($entity);
  }

}
