<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;

class NodeGenerator extends ContentEntityGenerator {
  public function supportsGeneration($object) {
    if (!parent::supportsGeneration($object)) {
      return FALSE;
    }

    return $object->id() == 'node';
  }

  protected function getBaseMapping($object, array $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $mapping = parent::getBaseMapping($object, $properties, $settings, $result, $componentResult);

    foreach ([
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'promote',
      'sticky',
      'default_langcode',
      'revision_translation_affected',
      'path',
    ] as $key) {
      if (isset($mapping[$key])) {
        unset($mapping[$key]);
      }
    }

    return $mapping;
  }
}
