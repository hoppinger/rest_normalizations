<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentGenerator\Field\TextFieldGenerator as BaseTextFieldGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;


class TextFieldGenerator extends BaseTextFieldGenerator {
  protected function getItemProperties($object, Settings $settings, Result $result, ComponentResult $component_result) {
    $properties = parent::getItemProperties($object, $settings, $result, $component_result);
    $properties['processed'] = 'string';
    return $properties;
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    return 'processed';
  }
}