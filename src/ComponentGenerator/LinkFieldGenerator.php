<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentGenerator\Field\FieldGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;

class LinkFieldGenerator extends FieldGenerator {
  protected $supportedFieldType = ['link'];

  protected function getItemProperties($object, Settings $settings, Result $result, ComponentResult $component_result) {
    $properties = parent::getItemProperties($object, $settings, $result, $component_result);
    $properties['processed_url'] = 'string';
    return $properties;
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    return [
      'title',
      'url' => 'processed_url',
    ];
  }
}