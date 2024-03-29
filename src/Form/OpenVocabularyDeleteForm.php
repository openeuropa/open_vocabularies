<?php

declare(strict_types=1);

namespace Drupal\open_vocabularies\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for deleting an open vocabulary entity.
 */
class OpenVocabularyDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $associations = $this->entityTypeManager->getStorage('open_vocabulary_association')
      ->loadAssociationsByVocabulary($this->entity->id());

    if (empty($associations)) {
      return parent::buildForm($form, $form_state);
    }

    $form['#title'] = $this->t('Cannot delete the vocabulary %vocabulary', [
      '%vocabulary' => $this->entity->label(),
    ]);
    $form['description']['warning'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('%vocabulary vocabulary is used by one or more associations and it cannot be deleted.', [
        '%vocabulary' => $this->entity->label(),
      ]),
      '#suffix' => '</p>',
    ];

    $items = [];
    foreach ($associations as $association) {
      $items[] = [
        '#type' => 'inline_template',
        '#template' => '{{ label }} (<em>{{ name }}</em>)',
        '#context' => [
          'label' => $association->label(),
          'name' => $association->getName(),
        ],
      ];
    }
    $form['description']['associations'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Associations referencing this vocabulary:'),
      '#items' => $items,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => ConfirmFormHelper::buildCancelLink($this, $this->getRequest()),
    ];

    return $form;
  }

}
