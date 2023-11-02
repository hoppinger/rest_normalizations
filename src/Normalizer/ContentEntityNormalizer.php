<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer as BaseNormalizer;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\media\Entity\Media;

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
    if($this->exclude_operations) {
      foreach ($this->exclude_operations as $exclude_operation) {
        $exclude_operation_regex = '/' . implode('.*', array_map(function($x) {
          return preg_quote($x, '/');
        }, explode('*', $exclude_operation))) . '/';
        if (preg_match($exclude_operation_regex, $entity_type_id)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  public function normalize($entity, $format = NULL, array $context = []) {
    $context += [
      'account' => NULL,
    ];

    $fields = [
      'nid', 'langcode', 'type', 'status', 'title', 'created', 'changed', 'moderation_state', 'type',
      'metatag', 'path', 'tid', 'name', 'description', 'parent', 'weight', 'default_langcode', 'revision_id'
    ];

    $data = [];
    if(!isset($context['level'])) {
      $context['level'] = 1;
    }

    /** @var \Drupal\Core\Entity\Entity $entity */
    foreach (TypedDataInternalPropertiesHelper::getNonInternalProperties($entity->getTypedData()) as $name => $field_items) {
      $normalize = FALSE;
      // if ($field_items->access('view', $context['account'])) {
      //   $data[$name] = $this->serializer->normalize($field_items, $format, $context);
      // }

      if ($field_items->access('view', $context['account'])) {
        if ($entity instanceof Media) {
          $normalize = TRUE;
        }
        elseif(str_starts_with($name, 'field_') && $context['level'] == 1) {
          $normalize = TRUE;
        }
        elseif($entity instanceof Paragraph && !in_array($name, $fields)) {
          continue;
        }
        elseif(in_array($name, $fields)) {
          $normalize = TRUE;
        }
      }

      if($normalize) {
        $data[$name] = $this->serializer->normalize($field_items, $format, $context);
      }
    }

    $data['language_links'] = [];
    foreach ($entity->getTranslationLanguages() as $language) {
      if ($entity->hasLinkTemplate('canonical') && $url = $entity->getTranslation($language->getId())->toUrl('canonical')->toString(TRUE)) {
        $data['language_links'][$language->getId()] = $url->getGeneratedUrl();
      }
    }

    $currentUser = \Drupal::currentUser();

    if (!$this->operationsExcluded($entity->getEntityTypeId()) && $currentUser->hasPermission('view entity operations in rest')) {
      try {
        $listBuilder = $this->entityTypeManager->getListBuilder($entity->getEntityTypeId());
      } catch (InvalidPluginDefinitionException $e) {
        return $data;
      }
      
      $operations = $listBuilder->getOperations($entity);
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
