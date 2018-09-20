<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer as BaseNormalizer;

class ContentEntityNormalizer extends BaseNormalizer {
  public function normalize($object, $format = NULL, array $context = []) {
    $data = parent::normalize($object, $format, $context);

    $data['language_links'] = [];
    foreach ($object->getTranslationLanguages() as $language) {
      $data['language_links'][$language->getId()] = $object->getTranslation($language->getId())->url('canonical');
    }

    return $data;
  }
}
