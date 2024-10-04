<?php

declare(strict_types=1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of vocabulary associations.
 */
class OpenVocabularyAssociationListBuilder extends DraggableListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field widget manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetManager;

  /**
   * Constructs a new OpenVocabularyAssociationListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Field\WidgetPluginManager $widgetManager
   *   The field widget manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entityTypeManager, WidgetPluginManager $widgetManager, MessengerInterface $messenger) {
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = $entityTypeManager;
    $this->widgetManager = $widgetManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.widget'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'open_vocabulary_association_list_builder_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['name'] = $this->t('Machine name');
    $header['widget_type'] = $this->t('Widget type');
    $header['vocabulary'] = $this->t('Vocabulary');
    $header['bundles'] = $this->t('Entity types');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $entity */
    $row['label'] = $entity->label();
    $row['name'] = [
      '#markup' => $entity->getName(),
    ];
    $widget_type_definition = $this->widgetManager->getDefinition($entity->getWidgetType());
    $row['widget_type'] = [
      '#markup' => $widget_type_definition['label'],
    ];
    $vocabulary = $this->entityTypeManager->getStorage('open_vocabulary')->load($entity->getVocabulary());
    $row['vocabulary'] = [
      '#markup' => $vocabulary->label(),
    ];
    $entities_and_bundles = $this->getAssociatedEntityTypeBundles($entity->getFields());
    $row['bundles'] = [
      '#type' => 'inline_template',
      '#template' => '
        {% for entity in entities %}
           <strong>{{ entity.entity_type_label }}</strong>{% if entity.bundles|length > 0 %}: {{ entity.bundles|join(", ") }}{% endif %}
           <br/>
        {% endfor %}
      ',
      '#context' => [
        'entities' => $entities_and_bundles,
      ],
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->messenger->addStatus($this->t('The order of the vocabulary associations has been updated.'));
  }

  /**
   * Generates an entity types and bundles array the vocabulary associated with.
   *
   * @param array $items
   *   An array of fields associated with.
   *
   * @return array
   *   Array containing the entity type and bundle labels for render.
   */
  protected function getAssociatedEntityTypeBundles(array $items): array {
    $output = [];
    $bundles_per_entity = [];

    // Process the input array to group bundles by entity type.
    foreach ($items as $item) {
      [$entity_type_id, $bundle_id] = explode('.', $item);
      $bundles_per_entity[$entity_type_id][] = $bundle_id;
    }

    // Loop through each entity type and get the labels.
    foreach ($bundles_per_entity as $entity_type_id => $bundle_ids) {
      // Get the label from the entity type definition.
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $entity_type_label = $entity_type->getLabel() ?: $entity_type->id();

      // Get the bundle label from the bundle entity type storage.
      $bundle_entity_type = $entity_type->getBundleEntityType();
      $bundle_labels = [];
      if ($bundle_entity_type) {
        $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type);
        foreach (array_unique($bundle_ids) as $bundle_id) {
          $bundle = $bundle_storage->load($bundle_id);
          if ($bundle) {
            $bundle_labels[] = $bundle->label() ?: $bundle->id();
          }
        }
      }

      // Add the entity type and its bundle labels to the output array.
      $output[] = [
        'entity_type_label' => $entity_type_label,
        'bundles' => $bundle_labels,
      ];
    }

    return $output;
  }

}
