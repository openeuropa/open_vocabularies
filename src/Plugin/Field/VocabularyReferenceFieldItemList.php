<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field;

use Drupal\Core\Field\FieldItemList;

/**
 * Defines a item list class for vocabulary reference fields.
 */
class VocabularyReferenceFieldItemList extends FieldItemList implements VocabularyReferenceFieldItemListInterface {

  /**
   * {@inheritdoc}
   */
  public function filterValuesByTargetAssociation(string $association_id): VocabularyReferenceFieldItemListInterface {
    $this->filter(function ($item) use ($association_id): bool {
      return $item->target_association !== $association_id;
    });

    return $this;
  }

}
