<?php

declare(strict_types=1);

namespace Drupal\open_vocabularies;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Storage handler for the Open Vocabulary Association entities.
 */
class OpenVocabularyAssociationStorage extends ConfigEntityStorage implements OpenVocabularyAssociationStorageInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a OpenVocabularyAssociationStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $memory_cache
   *   The memory cache backend.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, EntityFieldManagerInterface $entity_field_manager, ?MemoryCacheInterface $memory_cache = NULL) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache);

    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('entity_field.manager'),
      $container->get('entity.memory_cache')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadAssociationsByField(string $field_id): array {
    $query = $this->getQuery();
    $query->condition('fields.*', $field_id);
    $query->sort('weight');
    $results = $query->execute();

    if (empty($results)) {
      return [];
    }

    return $this->loadMultiple($results);
  }

  /**
   * {@inheritdoc}
   */
  public function loadAssociationsByVocabulary(string $vocabulary_id): array {
    $query = $this->getQuery();
    $query->condition('vocabulary', $vocabulary_id);
    $query->sort('weight');
    $results = $query->execute();

    if (empty($results)) {
      return [];
    }

    return $this->loadMultiple($results);
  }

  /**
   * Returns the next vocabulary association weight.
   *
   * @return int
   *   The next weight value.
   */
  protected function getNextVocabularyAssociationWeight(): int {
    $vocabulary_associations = $this->getSortedItems();
    if (empty($vocabulary_associations)) {
      return 0;
    }

    $last = end($vocabulary_associations);
    $weight = $last->getWeight();

    // If the weight is less than 0, we start with 0 to allow some buffer to
    // items that are potentially pre-shipped.
    return $weight < 0 ? 0 : $weight + 1;
  }

  /**
   * Loads and sorts the vocabulary associations by weight.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface[]
   *   The sorted vocabulary associations.
   */
  protected function getSortedItems(): array {
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface[] $vocabulary_associations */
    $vocabulary_associations = $this->loadMultiple();

    if (empty($vocabulary_associations)) {
      return [];
    }

    uasort($vocabulary_associations, function (OpenVocabularyAssociationInterface $a, OpenVocabularyAssociationInterface $b) {
      return ($a->get('weight') <=> $b->get('weight'));
    });

    return $vocabulary_associations;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity) {
    parent::doPreSave($entity);
    if ($entity->isNew()) {
      $entity->set('weight', $this->getNextVocabularyAssociationWeight());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update) {
    parent::doPostSave($entity, $update);

    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    parent::doDelete($entities);

    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

}
