<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal;

class EntityReferenceFieldItemTargetNormalizer extends EntityReferenceFieldItemNormalizer {
  /**
   * @var string[]
   */
  protected $target_identifiers;

  public function __construct(LanguageManagerInterface $languageManager, $target_identifiers) {
    parent::__construct($languageManager);
    $this->target_identifiers = $target_identifiers;
  }

  public function supportsNormalization($data, $format = NULL) {
    if (!parent::supportsNormalization($data, $format)) {
      return FALSE;
    }

    $entity = $data->get('entity')->getValue();
    if (!$entity) {
      return FALSE;
    }

    $field_entity = $data->getEntity();
    $field_entity_identifiers = array_filter([
      $entity->getEntityTypeId(),
      $field_entity->getEntityTypeId() . '-' . $data->getFieldDefinition()->getName(),
      $field_entity->bundle() ? (
        $field_entity->getEntityTypeId() . '-' . $field_entity->bundle() . '-' . $data->getFieldDefinition()->getName()
      ) : NULL,
    ]);

    return !!array_intersect($field_entity_identifiers, $this->target_identifiers);
  }

  public function normalize($field_item, $format = NULL, array $context = []) {
    $values = parent::normalize($field_item, $format, $context);

    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $field_item->get('entity')->getValue()) {

      if ($entity instanceof TranslatableInterface) {
        $entity = Drupal::entityManager()->getTranslationFromContext($entity, $langcode);
      }
      $values['target'] = $this->serializer->normalize($entity, $format, $context);
    }

    return $values;
  }
}
