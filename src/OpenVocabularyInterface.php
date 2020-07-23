<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a vocabulary entity.
 */
interface OpenVocabularyInterface extends ConfigEntityInterface {

  /**
   * Returns the vocabulary description.
   *
   * @return string
   *   The description text.
   */
  public function getDescription(): ?string;

  /**
   * Returns the handler ID, or NULL if not specified.
   *
   * @return string|null
   *   The handler ID.
   */
  public function getHandler(): ?string;

  /**
   * Returns the handler settings.
   *
   * @return array
   *   The handler settings array.
   */
  public function getHandlerSettings(): array;

}
