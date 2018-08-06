<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentGenerator\Entity\EntityGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;

class ParagraphGenerator extends EntityGenerator {
  public function supportsGeneration($object) {
    if (!parent::supportsGeneration($object)) {
      return FALSE;
    }

    return $object->id() == 'paragraph';
  }

  protected function getBaseMapping($object, array $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $mapping = parent::getBaseMapping($object, $properties, $settings, $result, $componentResult);

    foreach ([
      'status',
      'created',
      'revision_uid',
      'parent_id',
      'parent_type',
      'parent_field_name',
      'behavior_settings',
      'default_langcode',
      'revision_translation_affected',
      'path',
      'language',
      'uid',
    ] as $key) {
      if (isset($mapping[$key])) {
        unset($mapping[$key]);
      }
    }

    return $mapping;
  }
}