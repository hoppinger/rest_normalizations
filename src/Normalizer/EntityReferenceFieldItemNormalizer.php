<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal;

class EntityReferenceFieldItemNormalizer extends FieldItemNormalizer {
  protected $supportedInterfaceOrClass = EntityReferenceItem::class;

  /**
   * @var LanguageManagerInterface
   */
  protected $languageManager;

  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  public function normalize($field_item, $format = NULL, array $context = []) {
    $values = parent::normalize($field_item, $format, $context);

    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $field_item->get('entity')->getValue()) {
      $values['target_type'] = $entity->getEntityTypeId();
      // Add the target entity UUID to the normalized output values.
      $values['target_uuid'] = $entity->uuid();

      if ($entity instanceof TranslatableInterface) {
        $entity = \Drupal::service('entity.repository')->getTranslationFromContext($entity, $langcode);
      }

      // Add a 'url' value if there is a reference and a canonical URL. Hard
      // code 'canonical' here as config entities override the default $rel
      // parameter value to 'edit-form.
      if ($entity->hasLinkTemplate('canonical') && !$entity->isNew() && $url = $entity->toUrl('canonical')->toString(TRUE)) {
        $values['url'] = $url->getGeneratedUrl();
      }

      $values['target_label'] = $entity->label();
    }

    if (isset($context[CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY])) {
      $context[CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY]->addCacheableDependency($field_item);
    }

    return $values;
  }
}
