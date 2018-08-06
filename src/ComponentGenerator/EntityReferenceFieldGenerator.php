<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;
use Drupal\ts_generator\ComponentGenerator\Field\EntityReferenceFieldGenerator as BaseEntityReferenceFieldGenerator;

class EntityReferenceFieldGenerator extends BaseEntityReferenceFieldGenerator {
  protected function getItemProperties($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */

    $properties = parent::getItemProperties($object, $settings, $result, $component_result);

    $properties['target_label'] = 'string';

    return $properties;
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $mapping = (array) parent::getItemMapping($object, $properties, $settings, $result, $componentResult);
    $mapping['label'] = 'target_label';
    return $mapping;
  }
}