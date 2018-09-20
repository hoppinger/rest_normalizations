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

  protected function getBaseProperties($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $properties = parent::getBaseProperties($object, $settings, $result, $componentResult);

    $properties['language_links'] = $this->generateLanguageLinks($settings, $result, $componentResult);

    return $properties;
  }
}
