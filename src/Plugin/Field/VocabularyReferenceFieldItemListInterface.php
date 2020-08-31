<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Interface for vocabulary reference field item lists.
 */
interface VocabularyReferenceFieldItemListInterface extends FieldItemListInterface {

  /**
   * Filters out values of a specific vocabulary association.
   *
   * @param string $association_id
   *   The target vocabulary association ID.
   *
   * @return $this
   */
  public function filterValuesByTargetAssociation(string $association_id): VocabularyReferenceFieldItemListInterface;

}
