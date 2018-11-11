<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer as BaseNormalizer;

class ContentEntityNormalizer extends BaseNormalizer {
  public function normalize($object, $format = NULL, array $context = []) {
    $data = parent::normalize($object, $format, $context);

    $data['language_links'] = [];
    foreach ($object->getTranslationLanguages() as $language) {
      $data['language_links'][$language->getId()] = $object->getTranslation($language->getId())->url('canonical');
    }

    $currentUser = \Drupal::currentUser();

    if ($currentUser->hasPermission('view entity operations in rest')) {
      try {
        $listBuilder = $this->entityManager->getListBuilder($object->getEntityTypeId());
      } catch (InvalidPluginDefinitionException $e) {
        return $data;
      }
      
      $data['entity_operations'] = $listBuilder->getOperations($object);

      if (isset($context[CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY])) {
        $context[CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY]->addCacheContexts(['user']);
      }
    }

    return $data;
  }
}
