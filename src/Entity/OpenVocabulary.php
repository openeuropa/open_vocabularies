<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\open_vocabularies\OpenVocabularyInterface;

/**
 * Defines the vocabulary entity type.
 *
 * @ConfigEntityType(
 *   id = "open_vocabulary",
 *   label = @Translation("Vocabulary"),
 *   label_collection = @Translation("Vocabularies"),
 *   label_singular = @Translation("vocabulary"),
 *   label_plural = @Translation("vocabularies"),
 *   label_count = @PluralTranslation(
 *     singular = "@count vocabulary",
 *     plural = "@count vocabularies",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\open_vocabularies\OpenVocabularyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\open_vocabularies\Form\OpenVocabularyForm",
 *       "edit" = "Drupal\open_vocabularies\Form\OpenVocabularyForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "open_vocabulary",
 *   admin_permission = "administer open vocabularies",
 *   links = {
 *     "collection" = "/admin/structure/open-vocabulary",
 *     "add-form" = "/admin/structure/open-vocabulary/add",
 *     "edit-form" = "/admin/structure/open-vocabulary/{open_vocabulary}",
 *     "delete-form" = "/admin/structure/open-vocabulary/{open_vocabulary}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "handler",
 *     "handler_settings"
 *   }
 * )
 */
class OpenVocabulary extends ConfigEntityBase implements OpenVocabularyInterface {

  /**
   * The vocabulary ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The vocabulary label.
   *
   * @var string
   */
  protected $label;

  /**
   * The vocabulary description.
   *
   * @var string
   */
  protected $description;

  /**
   * The vocabulary reference handler ID.
   *
   * @var string
   */
  protected $handler;

  /**
   * The vocabulary reference handler settings.
   *
   * @var array
   */
  protected $handler_settings = [];

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getHandler(): ?string {
    return $this->handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getHandlerSettings(): array {
    return $this->handler_settings;
  }

}
