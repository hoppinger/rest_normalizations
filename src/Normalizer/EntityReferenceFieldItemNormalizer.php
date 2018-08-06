<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal;

class EntityReferenceFieldItemNormalizer extends FieldItemNormalizer {
  protected $supportedInterfaceOrClass = EntityReferenceItem::class;

  public function normalize($field_item, $format = NULL, array $context = []) {
    $values = parent::normalize($field_item, $format, $context);

    $langcode = $field_item->getLangcode();

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if ($entity = $field_item->get('entity')->getValue()) {
      $values['target_type'] = $entity->getEntityTypeId();
      // Add the target entity UUID to the normalized output values.
      $values['target_uuid'] = $entity->uuid();

      if ($entity instanceof TranslatableInterface) {
        $entity = Drupal::entityManager()->getTranslationFromContext($entity, $langcode);
      }

      // Add a 'url' value if there is a reference and a canonical URL. Hard
      // code 'canonical' here as config entities override the default $rel
      // parameter value to 'edit-form.
      if ($url = $entity->url('canonical')) {
        $values['url'] = $url;
      }

      $values['target_label'] = $entity->label();
    }

    return $values;
  }
}