<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\open_vocabularies\Plugin\Field\ComputedVocabularyReferenceFieldItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Wrap entity hooks to generate and display vocabulary entity reference fields.
 */
class VocabularyReferenceFieldsManager implements ContainerInjectionInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The reference handler plugin manager.
   *
   * @var \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface
   */
  protected $referenceHandlerManager;

  /**
   * Creates a new instance of the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface $reference_handler_manager
   *   The vocabulary reference handler plugin manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, VocabularyReferenceHandlerPluginManagerInterface $reference_handler_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->referenceHandlerManager = $reference_handler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler')
    );
  }

  /**
   * Alters entity form displays to place the computed entity reference fields.
   *
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display
   *   The entity_form_display object that will be used to display the entity
   *   form components.
   * @param array $context
   *   An associative array containing:
   *   - entity_type: The entity type, e.g., 'node' or 'user'.
   *   - bundle: The bundle, e.g., 'page' or 'article'.
   *   - form_mode: The form mode; e.g., 'default', 'profile', 'register', etc.
   */
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    $association_storage = $this->entityTypeManager->getStorage('open_vocabulary_association');

    $fields = $this->entityFieldManager->getFieldDefinitions($form_display->getTargetEntityTypeId(), $form_display->getTargetBundle());
    $fields_to_hide = [];
    foreach ($fields as $field_name => $definition) {
      // Filter out fields that are not entity references provided by this
      // module as base fields.
      if (!$definition instanceof BaseFieldDefinition || $definition->getProvider() !== 'open_vocabularies' || $definition->getType() !== 'entity_reference') {
        continue;
      }

      $reference_field_name = $definition->getSetting('open_vocabulary_reference_field');
      // Run an additional check to make sure that this is an entity reference
      // pointing to a vocabulary reference field.
      if ($reference_field_name === NULL) {
        continue;
      }

      // Check if the vocabulary reference field had its widget placed in this
      // form display.
      $reference_display_data = $form_display->getComponent($reference_field_name);
      if ($reference_display_data === NULL) {
        continue;
      }

      // Load the association to extract the widget to use.
      $association = $association_storage->load($definition->getSetting('open_vocabulary_association'));

      // Place the entity reference in the form at the same position of the
      // vocabulary reference widget, with a small offset calculated based on
      // the weight of the association entity.
      $form_display->setComponent($field_name, [
        'type' => $association->getWidgetType(),
        'weight' => $reference_display_data['weight'] + ($association->getWeight() / 1000),
        'region' => $reference_display_data['region'],
      ]);

      // Mark this reference field widget to be removed from the form display.
      $fields_to_hide[$reference_field_name] = TRUE;
    }

    if (!empty($fields_to_hide)) {
      // Changes to the form display were made, so mark it as custom.
      // This will prevent re-initialisation when the entity is being hydrated
      // from cache. The form display is stored in the form cache in
      // \Drupal\Core\Entity\ContentEntityForm::init().
      // @see \Drupal\Core\Entity\EntityDisplayBase::__wakeup()
      $form_display->set('mode', EntityDisplayBase::CUSTOM_MODE);

      // Hide all the vocabulary reference widgets from the display.
      foreach (array_keys($fields_to_hide) as $field_name) {
        $form_display->removeComponent($field_name);
      }
    }
  }

  /**
   * Generates entity reference fields from the vocabulary associations.
   *
   * In order to allow other modules to declare open vocabulary reference fields
   * programmatically, we don't use hook_entity_bundle_field_info() as normally
   * fields should be declared in that hook.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $fields
   *   The array of bundle field definitions.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $bundle
   *   The bundle.
   */
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    $vocabulary_reference_fields = array_filter($fields, function ($field): bool {
      return $field->getType() === 'open_vocabulary_reference';
    });

    if (empty($vocabulary_reference_fields)) {
      return;
    }

    foreach ($vocabulary_reference_fields as $reference_field) {
      $extra_fields = $this->generateEntityReferenceFields($reference_field, $entity_type, $bundle);
      if (!empty($extra_fields)) {
        $fields += $extra_fields;
      }
    }
  }

  /**
   * Generates entity reference fields from vocabulary reference fields.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $reference_field
   *   The open vocabulary reference field.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $bundle
   *   The bundle we are generating fields for.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   An array of base field definitions.
   */
  protected function generateEntityReferenceFields(FieldDefinitionInterface $reference_field, EntityTypeInterface $entity_type, string $bundle): array {
    $vocabulary_storage = $this->entityTypeManager->getStorage('open_vocabulary');
    $fields = [];

    $associations = $this->loadAssociationsByField($reference_field->id());
    foreach ($associations as $association) {
      /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $vocabulary */
      $vocabulary = $vocabulary_storage->load($association->getVocabulary());
      $plugin = $this->referenceHandlerManager->getDefinition($vocabulary->getHandler());

      // Generate an unique name for the field.
      $field_name = $association->getName();
      $field_name .= '_' . substr(hash('sha256', $association->id()), 0, 10);
      $fields[$field_name] = BaseFieldDefinition::create('entity_reference')
        ->setLabel($association->label())
        ->setDescription($association->getHelpText())
        ->setTranslatable(FALSE)
        ->setRequired($association->isRequired())
        ->setCardinality($association->getCardinality())
        ->setSetting('target_type', $plugin['target_type'])
        ->setSetting('handler_settings', $vocabulary->getHandlerSettings())
        ->setSetting('open_vocabulary_association', $association->id())
        ->setSetting('open_vocabulary_reference_field', $reference_field->getName())
        ->setComputed(TRUE)
        ->setClass(ComputedVocabularyReferenceFieldItemList::class)
        ->setReadOnly(FALSE)
        // Hide the field by default. It will be placed based on its reference
        // field weight during hook_entity_form_display_alter().
        // @todo Allow users to choose if an association will have its fields
        //   configurable.
        ->setDisplayConfigurable('form', FALSE)
        // Mimic the data added to fields by entity_bundle_field_info().
        ->setProvider('open_vocabularies')
        ->setName($field_name)
        ->setTargetEntityTypeId($entity_type->id())
        ->setTargetBundle($bundle);
    }

    return $fields;
  }

  /**
   * Loads association entities that are related to a specific field.
   *
   * @param string $field_id
   *   The field ID.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface[]
   *   The loaded associations, ordered by weight.
   */
  protected function loadAssociationsByField(string $field_id): array {
    $storage = $this->entityTypeManager->getStorage('open_vocabulary_association');
    $query = $storage->getQuery();
    $query->condition('fields.*', $field_id);
    $query->sort('weight');
    $results = $query->execute();

    if (empty($results)) {
      return [];
    }

    return $storage->loadMultiple($results);
  }

}