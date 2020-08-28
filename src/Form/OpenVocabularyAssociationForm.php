<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Vocabulary association form.
 *
 * @property \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $entity
 */
class OpenVocabularyAssociationForm extends EntityForm {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The reference handler plugin manager.
   *
   * @var \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface
   */
  protected $referenceHandlerManager;

  /**
   * The field widget plugin manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetManager;

  /**
   * Instantiates a new instance of the form.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface $referenceHandlerManager
   *   The reference handler plugin manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Field\WidgetPluginManager $widgetManager
   *   The field widget plugin manager.
   */
  public function __construct(MessengerInterface $messenger, EntityTypeManagerInterface $entityTypeManager, VocabularyReferenceHandlerPluginManagerInterface $referenceHandlerManager, EntityFieldManagerInterface $entityFieldManager, WidgetPluginManager $widgetManager) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->referenceHandlerManager = $referenceHandlerManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->widgetManager = $widgetManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.widget')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t('Label of the vocabulary association.'),
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->getName(),
      '#machine_name' => [
        'exists' => [$this, 'nameExists'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['fields'] = [
      '#type' => 'select',
      '#title' => $this->t('Fields'),
      '#description' => $this->t('Select the field target of this association.'),
      '#multiple' => TRUE,
      '#options' => $this->getAvailableFields(),
      '#default_value' => $entity->getFields(),
      // @todo make required once we have the field type.
      '#required' => FALSE,
      '#disabled' => !$entity->isNew(),
    ];

    $form['widget_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget type'),
      '#options' => $this->widgetManager->getOptions('entity_reference'),
      '#default_value' => $entity->getWidgetType(),
      '#required' => TRUE,
    ];

    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#options' => $this->getVocabularies(),
      '#default_value' => $entity->getVocabulary(),
      '#required' => TRUE,
      '#disabled' => !$entity->isNew(),
    ];

    $form['predicate'] = [
      '#type' => 'select',
      '#title' => $this->t('Predicate'),
      '#options' => [
        // @todo update the predicates.
        'http://example.com/#contain' => $this->t('Contain'),
        'http://example.com/#about' => $this->t('About'),
      ],
      '#default_value' => $entity->getPredicate(),
      '#required' => TRUE,
    ];

    $form['cardinality_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed number of values'),
      '#attributes' => [
        'class' => [
          'container-inline',
          'fieldgroup',
          'form-composite',
        ],
      ],
    ];

    $form['cardinality_wrapper']['cardinality'] = [
      '#type' => 'select',
      '#title' => $this->t('Allowed number of values'),
      '#title_display' => 'invisible',
      '#options' => [
        'number' => $this->t('Limited'),
        OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED => $this->t('Unlimited'),
      ],
      '#default_value' => ($entity->getCardinality() === OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED) ? OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED : 'number',
      '#disabled' => !$entity->isNew(),
    ];

    $form['cardinality_wrapper']['cardinality_number'] = [
      '#type' => 'number',
      '#default_value' => is_int($entity->getCardinality()) && $entity->getCardinality() !== OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED ? $entity->getCardinality() : 1,
      '#min' => 1,
      '#title' => $this->t('Limit'),
      '#title_display' => 'invisible',
      '#size' => 2,
      '#states' => [
        'visible' => [
          ':input[name="cardinality"]' => ['value' => 'number'],
        ],
      ],
      '#required' => TRUE,
      '#disabled' => !$entity->isNew(),
    ];

    if ($entity->isNew()) {
      $form['cardinality_wrapper']['cardinality_number']['#states']['disabled'] = [
        ':input[name="cardinality"]' => ['value' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED],
      ];
    }

    $form['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required'),
      '#default_value' => $entity->isRequired(),
    ];

    $form['help_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Help text'),
      '#default_value' => $entity->getHelpText(),
      '#description' => $this->t('Help text to print under the field.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // Save only the values of the fields, without keys.
    $fields = $form_state->getValue('fields', []);
    $form_state->setValue('fields', array_values($fields));

    // Save the cardinality.
    if ($form_state->getValue('cardinality') === 'number' && $form_state->getValue('cardinality_number')) {
      $form_state->setValue('cardinality', $form_state->getValue('cardinality_number'));
    }

    return parent::buildEntity($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new vocabulary association %label.', $message_args)
      : $this->t('Updated vocabulary association %label.', $message_args);
    $this->messenger->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    // Clear all the field information to allow computed fields to be added or
    // updated.
    $this->entityFieldManager->clearCachedFieldDefinitions();

    return $result;
  }

  /**
   * Returns the existing vocabularies.
   *
   * @return array
   *   The vocabularies in select options format.
   */
  protected function getVocabularies(): array {
    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $storage */
    $storage = $this->entityTypeManager->getStorage('open_vocabulary');
    $vocabularies = $storage->loadMultiple();

    if (!$vocabularies) {
      return [];
    }

    $options = [];
    foreach ($vocabularies as $vocabulary) {
      $options[$vocabulary->id()] = $vocabulary->label();
    }

    return $options;
  }

  /**
   * Returns the available field types to use for the association.
   *
   * @return array
   *   The field type names in select option format.
   */
  protected function getAvailableFields(): array {
    $storage = $this->entityTypeManager->getStorage('field_config');
    $query = $storage->getQuery();
    $query->condition('field_type', 'open_vocabulary_reference');
    $results = $query->execute();

    $fields = [];
    if (!$results) {
      return $fields;
    }

    foreach ($storage->loadMultiple($results) as $field) {
      $label = $this->t('Field @field on entity @entity, bundle @bundle', [
        '@field' => $field->label(),
        '@entity' => $field->getTargetEntityTypeId(),
        '@bundle' => $field->getTargetBundle(),
      ]);
      $fields[$field->id()] = $label;
    }

    return $fields;
  }

  /**
   * Checks whether the a given association machine name exists.
   *
   * @param string $name
   *   The name to check.
   *
   * @return bool
   *   TRUE if exists, FALSE if it doesn't.
   */
  public function nameExists(string $name): bool {
    $storage = $this->entityTypeManager->getStorage('open_vocabulary_association');
    $entities = $storage->loadByProperties(['name' => $name]);

    return !empty($entities);
  }

}
