<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field that delegates data storage to a vocabulary reference field.
 */
class ComputedVocabularyReferenceFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $association = $this->getSetting('open_vocabulary_association');
    $field_name = $this->getSetting('open_vocabulary_reference_field');
    $entity = $this->getEntity();
    $existing = $entity->get($field_name)->getValue();

    $delta = 0;
    foreach ($existing as $item) {
      if (isset($item['target_association']) && $item['target_association'] === $association) {
        unset($item['target_association']);
        $this->list[$delta] = $this->createItem($delta, $item);
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
    parent::setValue($values, $notify);

    $association = $this->getSetting('open_vocabulary_association');
    $field_name = $this->getSetting('open_vocabulary_reference_field');
    $entity = $this->getEntity();
    $existing = $entity->get($field_name)->getValue();
    foreach ($existing as $delta => $item) {
      // Drop values.
      if (empty($item)) {
        unset($existing[$delta]);
      }
      if (isset($item['target_association']) && $item['target_association'] === $association) {
        unset($existing[$delta]);
      }
    }
    $entity->get($field_name)->setValue($existing);
    foreach ($values as $value) {
      $value['target_association'] = $association;
      $entity->get($field_name)->appendItem($value);
    }
  }

}
