<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field\FieldFormatter;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemListInterface;
use Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for vocabulary reference formatters.
 */
abstract class VocabularyReferenceFormatterBase extends FormatterBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityRepository;

  /**
   * @var \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface
   */
  protected $referenceHandlerManager;

  /**
   * Constructs a VocabularyReferenceFormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface $reference_handler_manager
   *   The reference handler plugin manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, VocabularyReferenceHandlerPluginManagerInterface $reference_handler_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->referenceHandlerManager = $reference_handler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler')
    );
  }

  /**
   * @todo maybe align with entity reference formatter approach
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase::getEntitiesToView()
   *
   * @param \Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemListInterface $items
   * @param string $langcode
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *
   * @return array
   */
  protected function getEntitiesToView(VocabularyReferenceFieldItemListInterface $items, string $langcode, CacheableMetadata $cacheability): array {
    $values = [];
    foreach ($items as $delta => $item) {
      $values[$item->target_association_id][$delta] = $item->target_id;
    }

    $association_storage = $this->entityTypeManager->getStorage('open_vocabulary_association');
    $vocabulary_storage = $this->entityTypeManager->getStorage('open_vocabulary');

    $associations = [];
    $groups = [];
    foreach ($values as $association_id => $entity_ids) {
      $association = $association_storage->load($association_id);
      $associations[$association_id] = $association;

      $vocabulary = $vocabulary_storage->load($association->getVocabulary());
      $definition = $this->referenceHandlerManager->getDefinition($vocabulary->getHandler());
      $entity_storage = $this->entityTypeManager->getStorage($definition['target_type']);
      $entities = $entity_storage->loadMultiple($entity_ids);

      foreach ($entity_ids as $delta => $id) {
        if (!isset($entities[$id])) {
          continue;
        }

        $entity = $entities[$id];
        if ($entity instanceof TranslatableInterface) {
          $entity = $this->entityRepository->getTranslationFromContext($entity, $langcode);
        }

        $access = $this->checkAccess($entity);
        $cacheability->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $groups[$association_id][$delta] = $entity;
        }
      }
    }

    uksort($groups, function ($a, $b) use ($associations): int {
      return $associations[$a]->getWeight() <=> $associations[$b]->getWeight();
    });

    return $groups;
  }

  protected function checkAccess(EntityInterface $entity): AccessResultInterface {
    return $entity->access('view', NULL, TRUE);
  }

}
