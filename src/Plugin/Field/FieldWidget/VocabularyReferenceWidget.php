<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'open_vocabulary_reference_widget' field widget.
 *
 * @FieldWidget(
 *   id = "open_vocabulary_reference_widget",
 *   label = @Translation("Open vocabulary reference widget"),
 *   description = @Translation("This widget does not render any form element. It can be used as placeholder to position the single reference fields on the entity form display."),
 *   field_types = {"open_vocabulary_reference"},
 *   multiple_values = TRUE
 * )
 */
class VocabularyReferenceWidget extends WidgetBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a VocabularyReferenceWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
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
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      $element['info'] = [
        '#prefix' => '<p>',
        '#markup' => $this->t('This widget does not render any form element. It can be used as placeholder to position the single reference fields on the entity form display.'),
        '#suffix' => '</p>',
      ];
    }
    else {
      $element['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('This widget does not render any form element. It can be used as placeholder to position the single reference fields on the entity form display.');

    return $summary;
  }

}
