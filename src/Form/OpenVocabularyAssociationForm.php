<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
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
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

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
   * @param \Drupal\Core\Field\WidgetPluginManager $widgetManager
   *   The field widget plugin manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   */
  public function __construct(MessengerInterface $messenger, EntityTypeManagerInterface $entityTypeManager, WidgetPluginManager $widgetManager, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->widgetManager = $widgetManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.widget'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
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

    $form['fields'] = $this->getAvailableFields();

    $form['widget_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget type'),
      '#options' => $this->widgetManager->getOptions('entity_reference'),
      '#default_value' => $entity->getWidgetType(),
      '#empty_value' => '',
      '#required' => TRUE,
    ];

    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#options' => $this->getVocabularies(),
      '#default_value' => $entity->getVocabulary(),
      '#empty_value' => '',
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
      '#empty_value' => '',
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
    $fields = $form_state->getValue('fields', []);
    // Save only the checked fields.
    $fields = array_filter($fields);
    // Ensure a consistent order of the fields, independent from the labels.
    // This also drops all the keys.
    sort($fields);
    $form_state->setValue('fields', $fields);

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
   * Returns the available fields for the association.
   *
   * @return array
   *   A list of field checkboxes, grouped by entity type.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function getAvailableFields(): array {
    $fields = [];
    $entity_types = [];
    foreach ($this->entityFieldManager->getFieldMapByFieldType('open_vocabulary_reference') as $entity_type_id => $map) {
      $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);

      // Store the entity label separate so we can sort on them later.
      $entity_types[$entity_type_id] = $entity_definition->getLabel() ?: $entity_type_id;

      // The field manager method returns the fields and on which bundles they
      // appear. Reverse this by storing in each bundle which fields are
      // present. This is needed to determine which label to use later on.
      foreach ($map as $field_name => $data) {
        if (empty($data['bundles'])) {
          continue;
        }

        foreach ($data['bundles'] as $bundle_id) {
          $fields[$entity_type_id][$bundle_id][] = $field_name;
        }
      }
    }

    $build = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fields'),
      // Specify that is required so the fieldset will show a visual clue.
      '#required' => TRUE,
      // Run the required validation.
      '#element_validate' => [[static::class, 'validateFields']],
    ];

    // Sort the entity types by label.
    natcasesort($entity_types);
    foreach ($entity_types as $entity_type_id => $entity_label) {
      $build['groups'][$entity_type_id] = [
        '#type' => 'fieldgroup',
        '#title' => $entity_label,
        '#attributes' => [
          'class' => [
            'fieldgroup',
            'form-composite',
          ],
        ],
      ];

      foreach ($fields[$entity_type_id] as $bundle_id => $field_names) {
        $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        $bundle_label = $bundle_info[$bundle_id]['label'] ?: $bundle_id;
        $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
        $bundle_has_only_one_field = count($field_names) === 1;

        foreach ($field_names as $field_name) {
          // Generate the unique identifier for the field.
          $identifier = implode('.', [$entity_type_id, $bundle_id, $field_name]);
          $checked = in_array($identifier, $this->entity->getFields());

          $field_label = $definitions[$field_name]->getLabel() ?: $field_name;
          if ($bundle_has_only_one_field) {
            $title = $bundle_label;
          }
          else {
            // Since more than one field is present on the same bundle, identify
            // them by generating a title that uses bundle and field label.
            $title = new FormattableMarkup('@bundle (@field)', [
              '@bundle' => $bundle_label,
              '@field' => $field_label,
            ]);
          }

          $build['groups'][$entity_type_id][$identifier] = [
            // Place all the checkboxes under the "fields" key so it's easier to
            // retrieve values from the form state.
            '#parents' => ['fields', $identifier],
            '#type' => 'checkbox',
            '#title' => $title,
            '#return_value' => $identifier,
            '#default_value' => $checked ? $identifier : NULL,
            '#disabled' => !$this->entity->isNew() && $checked,
          ];
        }

        // Sort the checkboxes by their title.
        uasort($build['groups'][$entity_type_id], ['\Drupal\Component\Utility\SortArray', 'sortByTitleProperty']);
      }
    }

    return $build;
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

  /**
   * Element validate for the fields section.
   *
   * Enforces that at least one field is selected.
   *
   * @param array $elements
   *   The element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateFields(array &$elements, FormStateInterface $form_state, array $complete_form): void {
    $fields = array_filter($form_state->getValue('fields', []));
    if (empty($fields)) {
      $form_state->setError($elements, t('Select at least one entry from the @label section.', [
        '@label' => $elements['#title'],
      ]));
    }
  }

}
