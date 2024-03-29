<?php

declare(strict_types=1);

namespace Drupal\open_vocabularies\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;

/**
 * Defines the vocabulary association entity type.
 *
 * @ConfigEntityType(
 *   id = "open_vocabulary_association",
 *   label = @Translation("Vocabulary association"),
 *   label_collection = @Translation("Vocabulary associations"),
 *   label_singular = @Translation("vocabulary association"),
 *   label_plural = @Translation("vocabulary associations"),
 *   label_count = @PluralTranslation(
 *     singular = "@count vocabulary association",
 *     plural = "@count vocabulary associations",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\open_vocabularies\OpenVocabularyAssociationListBuilder",
 *     "form" = {
 *       "add" = "Drupal\open_vocabularies\Form\OpenVocabularyAssociationForm",
 *       "edit" = "Drupal\open_vocabularies\Form\OpenVocabularyAssociationForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "storage" = "Drupal\open_vocabularies\OpenVocabularyAssociationStorage"
 *   },
 *   config_prefix = "open_vocabulary_association",
 *   admin_permission = "administer open vocabulary associations",
 *   links = {
 *     "collection" = "/admin/structure/open-vocabulary-association",
 *     "add-form" = "/admin/structure/open-vocabulary-association/add",
 *     "edit-form" = "/admin/structure/open-vocabulary-association/{open_vocabulary_association}",
 *     "delete-form" = "/admin/structure/open-vocabulary-association/{open_vocabulary_association}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "name",
 *     "fields",
 *     "widget_type",
 *     "vocabulary",
 *     "cardinality",
 *     "required",
 *     "predicate",
 *     "help_text",
 *     "weight"
 *   }
 * )
 */
class OpenVocabularyAssociation extends ConfigEntityBase implements OpenVocabularyAssociationInterface {

  /**
   * The vocabulary association ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The vocabulary association label.
   *
   * @var string
   */
  protected $label;

  /**
   * The vocabulary association machine name.
   *
   * @var string
   */
  protected $name;

  /**
   * The field instance IDs to which this association is made.
   *
   * @var string[]
   */
  protected $fields = [];

  /**
   * The widget type to use.
   *
   * @var string
   */
  protected $widget_type;

  /**
   * The vocabulary ID.
   *
   * @var string
   */
  protected $vocabulary;

  /**
   * The association cardinality.
   *
   * @var int
   */
  protected $cardinality = 1;

  /**
   * Flag indicating whether the field is required.
   *
   * @var bool
   */
  protected $required = FALSE;

  /**
   * The association predicate.
   *
   * @var string
   */
  protected $predicate;

  /**
   * The help text to show on the widget.
   *
   * @var string
   */
  protected $help_text;

  /**
   * The vocabulary association weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ShortMethodName)
   */
  public function id() {
    return $this->getVocabulary() . '.' . $this->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields(): array {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetType(): ?string {
    return $this->widget_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getVocabulary(): ?string {
    return $this->vocabulary;
  }

  /**
   * {@inheritdoc}
   */
  public function getCardinality(): int {
    return (int) $this->cardinality;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    return (bool) $this->required;
  }

  /**
   * {@inheritdoc}
   */
  public function getPredicate(): ?string {
    return $this->predicate;
  }

  /**
   * {@inheritdoc}
   */
  public function getHelpText(): ?string {
    return $this->help_text;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($this->isNew()) {
      $this->id = $this->id();
    }

    parent::preSave($storage);
  }

}
