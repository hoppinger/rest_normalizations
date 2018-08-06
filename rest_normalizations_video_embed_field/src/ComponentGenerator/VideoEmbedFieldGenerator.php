<?php

namespace Drupal\rest_normalizations_video_embed_field\ComponentGenerator;

use Drupal\ts_generator\ComponentGenerator\Field\FieldGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;

class VideoEmbedFieldGenerator extends FieldGenerator {
  protected $supportedFieldType = ['video_embed_field'];

  protected function getItemProperties($object, Settings $settings, Result $result, ComponentResult $component_result) {
    $properties = parent::getItemProperties($object, $settings, $result, $component_result);
    $properties['video_type'] = 'string';
    $properties['video_id'] = 'string';
    $properties['iframe_url'] = 'string';

    return $properties;
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    return [
      'type' => 'video_type',
      'id' => 'video_id',
      'iframe_url'
    ];
  }
}