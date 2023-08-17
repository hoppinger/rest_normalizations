<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;

class EntityReferenceFieldItemFieldTargetNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * @var array[]
   */
  protected $field_target_identifiers;

  public function __construct(LanguageManagerInterface $languageManager, $field_target_identifiers) {
    parent::__construct($languageManager);
    $this->field_target_identifiers = $field_target_identifiers;
  }

  public function supportsNormalization($data, ?string $format = NULL, array $context = []): bool {
    if (!parent::supportsNormalization($data, $format, $context)) {
      return FALSE;
    }

    return !!$this->getFieldData($data);
  }

  protected function getFieldData($data) {
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

    foreach ($field_entity_identifiers as $field_entity_identifier) {
      if (isset($this->field_target_identifiers[$field_entity_identifier])) {
        return $this->field_target_identifiers[$field_entity_identifier];
      }
    }

    return FALSE;
  }

  public function normalize($field_item, $format = NULL, array $context = []): \ArrayObject|array|string|int|float|bool|null {
    $values = parent::normalize($field_item, $format, $context);
    $field_identifiers = $this->getFieldData($field_item);

    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $field_item->get('entity')->getValue()) {

      if ($entity instanceof TranslatableInterface) {
        $entity = \Drupal::service('entity.repository')->getTranslationFromContext($entity, $langcode);
      }

      $entity_type = $entity->getEntityType();

      $this->addCacheableDependency($context, $entity);

      $target = (object) [];

      /** @var \Drupal\Core\Entity\Entity $entity */
      foreach (TypedDataInternalPropertiesHelper::getNonInternalProperties($entity->getTypedData()) as $name => $field_items) {
        if ($field_items->access('view', $context['account']) && (
          in_array($name, $field_identifiers) ||
          $name == $entity_type->getKey('bundle')
        )) {
          $target->{$name} = $this->serializer->normalize($field_items, $format, $context);
        }
      }

      $values['target'] = $target;
    }
    return $values;
  }
}
