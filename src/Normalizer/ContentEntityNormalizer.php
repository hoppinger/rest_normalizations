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
      
      $operations = $listBuilder->getOperations($object);
      $data['entity_operations'] = $operations ? [] : new StdClass;
      foreach ($operations as $key => $operation) {
        $data['entity_operations'][$key] = [
          'title' => $operation['title'],
          'url' => is_string($operation['url']) ? $operation['url'] : $operation['url']->toString(),
          'weight' => !empty($operation['weight']) ? intval($operation['weight']) : 0
        ];
      }

      if (isset($context[CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY])) {
        $context[CacheableNormalizerInterface::SERIALIZATION_CONTEXT_CACHEABILITY]->addCacheContexts(['user']);
      }
    }

    return $data;
  }
}
