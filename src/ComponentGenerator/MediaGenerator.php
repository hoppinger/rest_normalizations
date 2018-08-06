<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentGenerator\Entity\EntityGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;

class MediaGenerator extends EntityGenerator {
  public function supportsGeneration($object) {
    if (!parent::supportsGeneration($object)) {
      return FALSE;
    }

    return $object->id() == 'media';
  }

  protected function getBaseMapping($object, array $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $mapping = parent::getBaseMapping($object, $properties, $settings, $result, $componentResult);

    foreach ([
      'language',
      'label',
      'revision_created',
      'revision_user',
      'revision_log_message',
      'status',
      'created',
      'changed',
      'default_langcode',
      'revision_translation_affected',
      'path',
      'uid',
    ] as $key) {
      if (isset($mapping[$key])) {
        unset($mapping[$key]);
      }
    }

    return $mapping;
  }
}