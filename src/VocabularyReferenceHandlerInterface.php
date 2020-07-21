<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;

/**
 * Interface for vocabulary_reference_handler plugins.
 */
interface VocabularyReferenceHandlerInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Returns the ID of the associated entity reference selection handler.
   *
   * @return \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
   *   The entity reference selection handler instance
   */
  public function getHandler(): SelectionInterface;

}
