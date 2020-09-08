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
    appendItem as traitAppendItem;
    removeItem as traitRemoveItem;
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
      if (!$item->isEmpty() && $item->target_association_id === $association_id) {
        $value = $item->getValue();
        // Remove the association value as the item is an entity reference.
        unset($value['target_association_id']);
        $this->list[$delta] = $this->createItem($delta, $value);
        $delta++;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($delta) {
    // Update all the values whenever a single item or property is changed.
    $this->updateVocabularyReferenceField();

    parent::onChange($delta);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    $this->traitSetValue($values, $notify);

    $this->updateVocabularyReferenceField();
  }

  /**
   * {@inheritdoc}
   */
  public function appendItem($value = NULL) {
    $return = $this->traitAppendItem($value);
    $this->updateVocabularyReferenceField();

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem($index) {
    $return = $this->traitRemoveItem($index);
    $this->updateVocabularyReferenceField();

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function filter($callback) {
    // Make sure that the values have been computed. This function is invoked
    // early when values are being prepared for forms.
    $this->ensureComputedValue();
    $return = parent::filter($callback);
    $this->updateVocabularyReferenceField();

    return $return;
  }

  /**
   * Updates the values stored in the vocabulary reference field.
   */
  protected function updateVocabularyReferenceField(): void {
    $association_id = $this->getSetting('open_vocabulary_association');
    $field_name = $this->getSetting('open_vocabulary_reference_field');

    /** @var \Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemListInterface $item_list */
    $item_list = $this->getEntity()->get($field_name);
    // Remove all the values belonging to this association, so we can re-append
    // them in the newly updated order.
    $item_list->filterTargetAssociationItems($association_id);
    foreach ($this->getValue() as $value) {
      $value['target_association_id'] = $association_id;
      $item_list->appendItem($value);
    }
  }

}
