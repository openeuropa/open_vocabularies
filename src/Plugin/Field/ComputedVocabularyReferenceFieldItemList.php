<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field that delegates data storage to a vocabulary reference field.
 */
class ComputedVocabularyReferenceFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait {
    setValue as traitSetValue;
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $association_id = $this->getSetting('open_vocabulary_association');
    $field_name = $this->getSetting('open_vocabulary_reference_field');

    /** @var \Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemListInterface $items */
    $items = $this->getEntity()->get($field_name);
    // We cannot use the ::append() method, as it will recursively call this
    // method again, since the value is not marked as computed yet. We keep
    // track of the delta manually and use the ::createItem() instead.
    // @see \Drupal\Core\TypedData\ComputedItemListTrait::appendItem()
    $delta = 0;
    foreach ($items as $item) {
      if (!$item->isEmpty() && $item->target_association === $association_id) {
        $value = $item->getValue();
        // Remove the association value as the item is an entity reference.
        unset($value['target_association']);
        $this->list[$delta] = $this->createItem($delta, $value);
        $delta++;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($delta) {
    // @todo Verify and implement.
    parent::onChange($delta);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    $this->traitSetValue($values, $notify);

    $association_id = $this->getSetting('open_vocabulary_association');
    $field_name = $this->getSetting('open_vocabulary_reference_field');

    /** @var \Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemListInterface $item_list */
    $item_list = $this->getEntity()->get($field_name);
    // Remove all the values belonging to this association, so we can re-append
    // them in the newly updated order.
    $item_list->filterValuesByTargetAssociation($association_id);
    foreach ($values as $value) {
      $value['target_association'] = $association_id;
      $item_list->appendItem($value);
    }
  }

}
