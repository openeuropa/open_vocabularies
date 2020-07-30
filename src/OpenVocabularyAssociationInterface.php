<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an vocabulary association entity type.
 */
interface OpenVocabularyAssociationInterface extends ConfigEntityInterface {

  /**
   * Value indicating that an association accepts an unlimited number of values.
   */
  const CARDINALITY_UNLIMITED = -1;

  /**
   * Returns the machine name of the vocabulary association.
   *
   * @return string|null
   *   The name.
   */
  public function getName(): ?string;

  /**
   * Returns the field names to which the association is made.
   *
   * @return array
   *   The fields.
   */
  public function getFields(): array;

  /**
   * Returns the widget type the association uses.
   *
   * @return string|null
   *   The widget type.
   */
  public function getWidgetType(): ?string;

  /**
   * Returns the associated vocabulary.
   *
   * @return string|null
   *   The vocabulary.
   */
  public function getVocabulary(): ?string;

  /**
   * Returns the association cardinality.
   *
   * @return int
   *   The cardinality.
   */
  public function getCardinality(): ?int;

  /**
   * Returns whether the association field is required or not.
   *
   * @return bool|null
   *   Whether it's required or not.
   */
  public function isRequired(): ?bool;

  /**
   * Returns the predicate.
   *
   * @return string|null
   *   The predicate.
   */
  public function getPredicate(): ?string;

  /**
   * Returns the help text.
   *
   * @return string|null
   *   The help text.
   */
  public function getHelpText(): ?string;

}
