<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Vocabulary form.
 *
 * @property \Drupal\open_vocabularies\OpenVocabularyInterface $entity
 */
class OpenVocabularyForm extends EntityForm {

  /**
   * The reference handler plugin manager.
   *
   * @var \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface
   */
  protected $referenceHandlerManager;

  /**
   * Instantiates a new instance of the form.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\open_vocabularies\VocabularyReferenceHandlerPluginManagerInterface $referenceHandlerManager
   *   The reference handler plugin manager.
   */
  public function __construct(MessengerInterface $messenger, VocabularyReferenceHandlerPluginManagerInterface $referenceHandlerManager) {
    $this->messenger = $messenger;
    $this->referenceHandlerManager = $referenceHandlerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('plugin.manager.open_vocabularies.vocabulary_reference_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the vocabulary.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [OpenVocabulary::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->getDescription(),
      '#description' => $this->t('Description of the vocabulary.'),
    ];

    // Retrieve the handler value from the entity.
    $handler_id = $this->entity->getHandler();

    $has_associations = $this->entityHasTargetingAssociations();
    $form['handler'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => $this->t('Vocabulary type'),
      '#options' => $this->referenceHandlerManager->getDefinitionsAsOptions(),
      '#default_value' => $handler_id,
      '#ajax' => [
        'callback' => [static::class, 'updateHandlerSettings'],
        'wrapper' => 'vocabulary-handler-settings-wrapper',
      ],
      '#disabled' => $has_associations,
    ];

    $form['handler_settings'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      '#id' => 'vocabulary-handler-settings-wrapper',
      '#process' => [[EntityReferenceItem::class, 'fieldSettingsAjaxProcess']],
      '#element_validate' => ['::validateSelectionPluginHandlerConfiguration'],
      // The selection handlers expect the form elements to be under a specific
      // array key.
      '#parents' => ['settings', 'handler_settings'],
    ];

    // Not all the handler settings are meant to be disabled, for example
    // the sorting options or adding a bundle. Show a message to the user,
    // warning them about the possible issues.
    if ($has_associations) {
      $form['handler_settings']['warning'] = [
        '#prefix' => '<em>',
        '#markup' => $this->t('<strong>Please note:</strong> this vocabulary is used by one or more associations. Changing the following settings can cause loss of data.'),
        '#suffix' => '</em>',
      ];
    }

    if ($handler_id) {
      /** @var \Drupal\open_vocabularies\VocabularyReferenceHandlerInterface $vocabulary_handler */
      $vocabulary_handler = $this->referenceHandlerManager->createInstance($handler_id, $this->entity->getHandlerSettings());
      $entity_reference_selection = $vocabulary_handler->getHandler();
      $form['handler_settings'] += $entity_reference_selection->buildConfigurationForm([], $form_state);

      // @todo Handle this in a wrapper plugin maybe?
      foreach (['auto_create', 'auto_create_bundle'] as $key) {
        if (isset($form['handler_settings'][$key])) {
          $form['handler_settings'][$key]['#access'] = FALSE;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // Whenever the handler plugin is changed, reset the submitted handler
    // settings so that any configuration of the old handler is not kept for the
    // new one. This has to happen before the entity is built, as both form
    // state and entity will be used when the form is rebuilt.
    if ($this->entity->getHandler() !== $form_state->getValue('handler')) {
      $form_state->unsetValue(['settings', 'handler_settings']);
    }

    // The selection handlers expect the form elements to be under a specific
    // array key. Move the handler settings up to match our entity property.
    $handler_settings = $form_state->getValue(['settings', 'handler_settings'], []);
    // Force auto create settings to disabled.
    $handler_settings['auto_create'] = FALSE;
    $handler_settings['auto_create_bundle'] = NULL;
    $form_state->setValue('handler_settings', $handler_settings);

    return parent::buildEntity($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new vocabulary %label.', $message_args)
      : $this->t('Updated vocabulary %label.', $message_args);
    $this->messenger->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

  /**
   * Ajax callback to update the handler settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The updated form element.
   */
  public static function updateHandlerSettings(array &$form, FormStateInterface $form_state): array {
    return $form['handler_settings'];
  }

  /**
   * Form element validation handler. Invokes the selection plugin's validation.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public function validateSelectionPluginHandlerConfiguration(array $form, FormStateInterface $form_state): void {
    $handler_id = $form_state->getValue('handler', NULL);
    if ($handler_id === NULL) {
      return;
    }

    $vocabulary_handler = $this->referenceHandlerManager->createInstance($handler_id);
    $vocabulary_handler->getHandler()->validateConfigurationForm($form, $form_state);
  }

  /**
   * Returns if any association entities are targeting the current vocabulary.
   *
   * @return bool
   *   True if any association references this vocabulary, false otherwise.
   */
  protected function entityHasTargetingAssociations(): bool {
    if ($this->entity->isNew()) {
      return FALSE;
    }

    $association_storage = $this->entityTypeManager->getStorage('open_vocabulary_association');
    return !empty($association_storage->loadAssociationsByVocabulary($this->entity->id()));
  }

}
