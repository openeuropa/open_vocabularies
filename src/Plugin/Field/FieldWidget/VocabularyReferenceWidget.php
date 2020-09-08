<?php

declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'open_vocabulary_reference_widget' field widget.
 *
 * @FieldWidget(
 *   id = "open_vocabulary_reference_widget",
 *   label = @Translation("Open vocabulary reference widget"),
 *   description = @Translation("This widget does not render any form element. It can be used as placeholder to position the single reference fields on the entity form display."),
 *   field_types = {"open_vocabulary_reference"},
 *   multiple_values = TRUE
 * )
 */
class VocabularyReferenceWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      $element['info'] = [
        '#prefix' => '<p>',
        '#markup' => $this->t('This widget does not render any form element. It can be used as placeholder to position the single reference fields on the entity form display.'),
        '#suffix' => '</p>',
      ];
    }
    else {
      $element['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('This widget does not render any form element. It can be used as placeholder to position the single reference fields on the entity form display.');

    return $summary;
  }

}
