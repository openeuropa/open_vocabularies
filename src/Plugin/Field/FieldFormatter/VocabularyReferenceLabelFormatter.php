<?php


declare(strict_types = 1);

namespace Drupal\open_vocabularies\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\open_vocabularies\Plugin\Field\VocabularyReferenceFieldItemListInterface;

/**
 * Plugin implementation of the 'entity reference label' formatter.
 *
 * @FieldFormatter(
 *   id = "vocabulary_reference_label",
 *   label = @Translation("Label"),
 *   description = @Translation("Display the label of the referenced entities."),
 *   field_types = {
 *     "open_vocabulary_reference"
 *   }
 * )
 */
class VocabularyReferenceLabelFormatter extends VocabularyReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'link' => TRUE,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['link'] = [
      '#title' => t('Link label to the referenced entity'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('link'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->getSetting('link') ? t('Link to the referenced entity') : t('No link');
    return $summary;
  }

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $association_storage = $this->entityTypeManager->getStorage('open_vocabulary_association');
    $cacheability = new CacheableMetadata();
    foreach ($this->getEntitiesToView($items, $langcode, $cacheability) as $association_id => $entities) {
      $items = [];
      foreach ($entities as $delta => $entity) {
        $items[] = $this->getEntityLabelRenderArray($entity);
      }

      $association = $association_storage->load($association_id);
      $item_list = [
        '#theme' => 'item_list',
        '#title' => $association->label(),
        '#items' => $items,
        '#association' => $association_id,
      ];

      CacheableMetadata::createFromObject($association)
        ->merge($cacheability)
        ->applyTo($item_list);

      $elements[] = $item_list;
    }

    return $elements;
  }

  protected function getEntityLabelRenderArray(EntityInterface $entity): array {
    $output_as_link = $this->getSetting('link');

    $label = $entity->label();
    // If the link is to be displayed and the entity has a uri, display a
    // link.
    if ($output_as_link && !$entity->isNew()) {
      try {
        $uri = $entity->toUrl();
      }
      catch (UndefinedLinkTemplateException $e) {
        // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
        // and it means that the entity type doesn't have a link template nor
        // a valid "uri_callback", so don't bother trying to output a link for
        // the rest of the referenced entities.
        $output_as_link = FALSE;
      }
    }

    if ($output_as_link && isset($uri) && !$entity->isNew()) {
      $element = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $uri,
        '#options' => $uri->getOptions(),
      ];
    }
    else {
      $element = ['#plain_text' => $label];
    }

    CacheableMetadata::createFromRenderArray($element)
      ->addCacheableDependency($entity)
      ->applyTo($element);

    return $element;
  }

}
