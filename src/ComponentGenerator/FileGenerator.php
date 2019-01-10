<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\ts_generator\ComponentGenerator\Entity\EntityGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;
use Symfony\Component\DependencyInjection\Container;

class FileGenerator extends EntityGenerator {
  public function supportsGeneration($object) {
    if (!parent::supportsGeneration($object)) {
      return FALSE;
    }

    return $object->id() == 'file';
  }

  protected function generateStyles($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $styles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
    $properties = [];
    foreach ($styles as $style) {
      $properties[$style->id()] = 'string';
    }

    return $this->generatePropertiesComponentResult($properties, 'Styles', 'ParsedStyles', 'styles_parser', NULL, $settings, $result);
  }

  protected function getBaseMapping($object, array $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $mapping = parent::getBaseMapping($object, $properties, $settings, $result, $componentResult);

    foreach ([
      'language',
      'uid',
      'uri',
      'filemime',
      'filesize',
      'status',
      'created',
      'changed'
    ] as $key) {
      if (isset($mapping[$key])) {
        unset($mapping[$key]);
      }
    }

    $mapping['url'] = 'uri';

    return $mapping;
  }

  protected function generateImage($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $stylesResult = $componentResult->getContext('styles');
    $baseResult = $componentResult->getContext('base');

    $name = Container::camelize($object->id()) . 'Image';

    $properties = [
      'style_urls' => $stylesResult,
    ];
    $mapping = [
      $baseResult,
      'style_urls'
    ];

    $imageResult = $this->generatePropertiesComponentResult($properties, $name, 'Parsed' . $name, Container::underscore($name) . '_parser', $mapping, $settings, $result);
    $imageResult->setComponent(
      'guard',
      $result->setComponent(
        'parser/' . Container::underscore($name) . '_guard',
        'const ' . Container::underscore($name) . '_guard = (t:' . $componentResult->getComponent('type') . '): t is ' . $imageResult->getComponent('type') . ' => (t as any).style_urls !== undefined'
      )
    );

    return $imageResult;
  }

  protected function generateOther($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $baseResult = $componentResult->getContext('base');

    $name = Container::camelize($object->id()) . 'Other';

    $properties = [];
    $mapping = [
      $baseResult,
    ];

    $otherResult = $this->generatePropertiesComponentResult($properties, $name, 'Parsed' . $name, Container::underscore($name) . '_parser', $mapping, $settings, $result);
    $otherResult->setComponent(
      'guard',
      $result->setComponent(
        'parser/' . Container::underscore($name) . '_guard',
        'const ' . Container::underscore($name) . '_guard = (t:' . $componentResult->getComponent('type') . '): t is ' . $otherResult->getComponent('type') . ' => (t as any).style_urls === undefined'
      )
    );

    return $otherResult;
  }

  public function generateTargetType($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $name = 'Parsed' . Container::camelize($object->id());

    $otherResult = $componentResult->getContext('other');
    $imageResult = $componentResult->getContext('image');

    return $result->setComponent('types/' . $name, 'type ' . $name . ' = ' . $imageResult->getComponent('target_type') . " | " . $otherResult->getComponent('target_type'));
  }

  public function generateType($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $name = Container::camelize($object->id());

    $otherResult = $componentResult->getContext('other');
    $imageResult = $componentResult->getContext('image');

    return $result->setComponent('types/' . $name, 'type ' . $name . ' = ' . $imageResult->getComponent('type') . " | " . $otherResult->getComponent('type'));
  }

  public function generateParser($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $object */

    $name =  $object->id() . '_parser';

    $otherResult = $componentResult->getContext('other');
    $imageResult = $componentResult->getContext('image');

    return $result->setComponent(
      'parser/' . $name,
      'const ' . $name . ' = (t: ' . $componentResult->getComponent('type') . '): ' . $componentResult->getComponent('target_type') . " => " . $imageResult->getComponent('guard') . '(t) ? ' . $imageResult->getComponent('parser') . '(t) : ' . $otherResult->getComponent('parser') . '(t)'
    );
  }

  protected function preGenerate($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    parent::preGenerate($object, $settings, $result, $componentResult);

    $styles = $componentResult->getContext('styles');
    if (!isset($styles)) {
      $styles = $this->generateStyles($object, $settings, $result, $componentResult);
      $componentResult->setContext('styles', $styles);
    }

    $image = $componentResult->getContext('image');
    if (!isset($image)) {
      $image = $this->generateImage($object, $settings, $result, $componentResult);
      $componentResult->setContext('image', $image);
    }

    $other = $componentResult->getContext('other');
    if (!isset($other)) {
      $other = $this->generateOther($object, $settings, $result, $componentResult);
      $componentResult->setContext('other', $other);
    }
  }
}
