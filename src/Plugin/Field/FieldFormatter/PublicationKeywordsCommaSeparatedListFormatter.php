<?php

namespace Drupal\copernicus_publication\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin implementation of the 'publication_keywords_comma_separated_list' formatter.
 *
 * @FieldFormatter(
 *   id = "publication_keywords_comma_separated_list",
 *   label = @Translation("Publication keywords comma separated list"),
 *   field_types = {
 *     "entity_reference",
 *   },
 * )
 */
class PublicationKeywordsCommaSeparatedListFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [
      '#plain_text' => '',
    ];

    $publicationEntity = $items->getEntity();

    //@TODO - delete "keywords" condition when copernicus_publication old table is deleted
    if ($publicationEntity->hasField('keywords')) {
      $keywordsField = $publicationEntity->get('keywords');
    } else {
      $keywordsField = $publicationEntity->get('field_keywords');
    }
    $keywordTermEntities = $keywordsField->referencedEntities();
    if (empty($keywordTermEntities)) {
      return $elements;
    }

    $keywords = '';
    foreach ($keywordTermEntities as $keywordTermEntity) {
      if (!$keywordTermEntity instanceof Term) {
        continue;
      }

      $keywords .= $keywordTermEntity->getName() . ', ';
    }

    $keywords = rtrim($keywords, ' ,');

    $elements = [
      '#plain_text' => $keywords,
    ];

    return $elements;
  }

}
