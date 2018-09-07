<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;

class FileFieldGenerator extends EntityReferenceTargetFieldGenerator {
  protected $supportedFieldType = ['image', 'file'];

  protected function getTargetProperty($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */

    $target_component_result = parent::getTargetProperty($object, $settings, $result, $component_result);

    $type = $object->getType();
    return $target_component_result->getContext($type == 'image' ? 'image' : 'other');
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $type = $object->getType();

    if ($type == 'image') {
      return [
        'alt',
        'image' => 'target'
      ];
    } else {
      return [
        'description',
        'file' => 'target'
      ];
    }
  }
}