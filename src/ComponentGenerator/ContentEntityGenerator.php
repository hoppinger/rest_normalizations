<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\ts_generator\ComponentGenerator\Entity\EntityGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;

class ContentEntityGenerator extends EntityGenerator {
  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, LanguageManagerInterface $languageManager) {
    parent::__construct($entityTypeManager, $entityFieldManager);

    $this->languageManager = $languageManager;
  }

  public function supportsGeneration($object) {
    return $object instanceof ContentEntityTypeInterface;
  }

  protected function generateLanguageObject(Settings $settings, Result $result) {
    return $this->generator->generate($this->languageManager, $settings, $result)->getComponent('type');
  }

  protected function generateLanguageLinks(Settings $settings, Result $result, ComponentResult $componentResult) {
    $language_type = $this->generateLanguageObject($settings, $result, $componentResult);

    $type = $result->setComponent('types/LanguageLinks', 'type LanguageLinks = { [K in ' . $language_type . ']?: string }');
    $target_type = $result->setComponent('types/ParsedLanguageLinks', 'type ParsedLanguageLinks = :/immutable/Map:<' . $language_type . ', string>');
    $parser = $result->setComponent('parser/language_links_parser', 'const language_links_parser = (l: ' . $type . '): ' . $target_type . ' => :/immutable/Map:<' . $language_type . ', string>(l)');

    return new ComponentResult([
      'type' => $type,
      'target_type' => $target_type,
      'parser' => $parser,
    ]);
  }

  protected function getOperationProperties(Settings $settings, Result $result, ComponentResult $componentResult) {
    return [
      'title' => 'string',
      'url' => 'string',
      'weight' => 'number'
    ];
  }

  protected function getOperationMapping($properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    return ['title', 'url'];
  }

  protected function generateOperation(Settings $settings, Result $result, ComponentResult $componentResult) {
    $properties = $this->getOperationProperties($settings, $result, $componentResult);

    return $this->generatePropertiesComponentResult(
      $properties,
      'EntityOperation',
      'ParsedEntityOperation',
      'entity_operation_parser',
      $this->getOperationMapping($properties, $settings, $result, $componentResult),
      $settings,
      $result
    );
  }

  protected function generateOperations(Settings $settings, Result $result, ComponentResult $componentResult) {
    $operation = $this->generateOperation($settings, $result, $componentResult);

    $type = $result->setComponent(
      'types/EntityOperations', 
      'interface EntityOperations { [key: string]: ' . $operation->getComponent('type') . '}'
    );
    $target_type = $result->setComponent(
      'types/ParsedEntityOperations',
      'type ParsedEntityOperations = ' . 
        ':/immutable/OrderedMap:<string, ' . $operation->getComponent('target_type') . '>'
    );
    $parser = $result->setComponent(
      'parser/entity_operations_parser', 
      'const entity_operations_parser = ' . 
        '(l: ' . $type . ' | undefined): ' . $target_type . ' => '. 
          'l ? :/immutable/OrderedMap:<string, ' . $operation->getComponent('type') . '>(l).sortBy(o => (o as ' . $operation->getComponent('type') . ').weight).map(o => ' . $operation->getComponent('parser') . '(o as ' . $operation->getComponent('type') . ')).toOrderedMap() : :/immutable/OrderedMap:<string, ' . $operation->getComponent('type') . '>()'
    );

    return new ComponentResult([
      'type' => $type . ' | undefined',
      'target_type' => $target_type,
      'parser' => $parser,
    ]);
  }

  protected function getBaseProperties($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $properties = parent::getBaseProperties($object, $settings, $result, $componentResult);

    $properties['language_links'] = $componentResult->getContext('language_links');
    $properties['entity_operations'] = $componentResult->getContext('operations');

    return $properties;
  }

  protected function preGenerate($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $object */

    // The things are used in the base properties, so need to be done before the entity preGenerate
    $language_links = $componentResult->getContext('language_links');
    if (!isset($language_links)) {
      $language_links = $this->generateLanguageLinks($settings, $result, $componentResult);
      $componentResult->setContext('language_links', $language_links);
    }

    $operations = $componentResult->getContext('operations');
    if (!isset($operations)) {
      $operations = $this->generateOperations($settings, $result, $componentResult);
      $componentResult->setContext('operations', $operations);
    }

    parent::preGenerate($object, $settings, $result, $componentResult);
  }
}
