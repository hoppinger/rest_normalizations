<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer as BaseNormalizer;


class ContentEntityNormalizer extends BaseNormalizer {
  /**
   * @var string[]
   */
  protected $exclude_operations;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeRepositoryInterface $entity_type_repository = NULL, EntityFieldManagerInterface $entity_field_manager = NULL, $exclude_operations) {
    parent::__construct($entity_type_manager, $entity_type_repository, $entity_field_manager);

    $this->exclude_operations = $exclude_operations;
  }

  protected function operationsExcluded($entity_type_id) {
    foreach ($this->exclude_operations as $exclude_operation) {
      $exclude_operation_regex = '/' . implode('.*', array_map(function($x) {
        return preg_quote($x, '/');
      }, explode('*', $exclude_operation))) . '/';
      if (preg_match($exclude_operation_regex, $entity_type_id)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  public function normalize($object, $format = NULL, array $context = []) {
    $data = parent::normalize($object, $format, $context);

    $data['language_links'] = [];
    foreach ($object->getTranslationLanguages() as $language) {
      if ($object->hasLinkTemplate('canonical') && $url = $object->getTranslation($language->getId())->toUrl('canonical')->toString(TRUE)) {
        $data['language_links'][$language->getId()] = $url->getGeneratedUrl();
      }
    }

    $currentUser = \Drupal::currentUser();

    if (!$this->operationsExcluded($object->getEntityTypeId()) && $currentUser->hasPermission('view entity operations in rest')) {
      try {
        $listBuilder = $this->entityTypeManager->getListBuilder($object->getEntityTypeId());
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
