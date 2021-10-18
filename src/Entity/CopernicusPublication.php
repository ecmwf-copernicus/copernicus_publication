<?php

namespace Drupal\copernicus_publication\Entity;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;

/**
 * Class CopernicusPublication.
 *
 * @ContentEntityType(
 *   id = "copernicus_publication",
 *   label = @Translation("Publication"),
 *   base_table = "copernicus_publication",
 *   data_table = "copernicus_publication_field_data",
 *   revision_table = "copernicus_publication_revision",
 *   revision_data_table = "copernicus_publication_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "vid",
 *     "label" = "title",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *     "published" = "status",
 *     "uid" = "uid",
 *   },
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\copernicus_publication\Form\CopernicusPublicationForm",
 *       "add" = "Drupal\copernicus_publication\Form\CopernicusPublicationForm",
 *       "edit" = "Drupal\copernicus_publication\Form\CopernicusPublicationForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\copernicus_publication\Entity\CopernicusPublicationEntityAccessControlHandler",
 *   },
 *   links = {
 *     "canonical" = "/publication/{copernicus_publication}",
 *     "add-form" = "/publication/add/",
 *     "edit-form" = "/publication/{copernicus_publication}/edit",
 *     "delete-form" = "/publication/{copernicus_publication}/delete",
 *     "collection" = "/admin/content/author",
 *   },
 *   admin_permission = "administer site configuration",
 *   fieldable = FALSE,
 *   field_ui_base_route = "copernicus_publication.settings",
 * )
 */
final class CopernicusPublication extends EditorialContentEntityBase implements CopernicusPublicationInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['abstract'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Abstract'))
      ->setRequired(FALSE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'publication_types' => 'publication_types',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
        'settings' => [
          'size' => '60',
        ],
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => FALSE,
        ],
        'label' => 'inline',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Published date'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDefaultValueCallback('Drupal\copernicus_publication\Entity\CopernicusPublication::getDefaultPublishedDate')
      ->setDisplayOptions('form', [
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'medium',
        ],
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['authors'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authors'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'copernicus_author')
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'settings' => [
          'allow_existing' => TRUE,
        ],
        'weight' => 4,
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => FALSE,
        ],
        'label' => 'inline',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['doi'] = BaseFieldDefinition::create('doi')
      ->setLabel(t('DOI'))
      ->setRequired(FALSE)
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'doi_default',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['keywords'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Keywords'))
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'publication_keywords' => 'publication_keywords',
        ],
        'auto_create' => TRUE,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => FALSE,
        ],
        'label' => 'inline',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['download'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Download'))
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE)
      ->setSetting('file_extensions', 'pdf')
      ->setSetting('description_field', TRUE)
      ->setSetting('file_directory', 'publications')
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'type' => 'file_default',
        'settings' => [
          'use_description_as_link_text' => TRUE,
        ],
        'label' => 'hidden',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The username of the author.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\copernicus_publication\Entity\CopernicusPublication::getCurrentUserId')
      ->setDisplayOptions('view', [
        'type' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 11,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the publication was created.'))
      ->setDisplayOptions('view', [
        'type' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 12,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the publication was last edited.'));

    return $fields;
  }

  /**
   * Default value callback for the uid base field definition.
   *
   * @see https://www.drupal.org/project/drupal/issues/2142515
   *
   * @return array
   *   An array containing the id of the current user.
   */
  public static function getCurrentUserId(): array {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Default value callback for the date base field definition.
   *
   * @return string
   *   The current date.
   */
  public static function getDefaultPublishedDate(): string {
    $currentTime = DrupalDateTime::createFromTimestamp(time());
    return $currentTime->format(DateTimeItem::DATE_STORAGE_FORMAT);
  }

}
