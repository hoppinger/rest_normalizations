<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\ts_generator\ComponentGenerator\UnionGenerator;
use Drupal\ts_generator\ComponentResult;
use Drupal\ts_generator\Result;
use Drupal\ts_generator\Settings;
use Symfony\Component\DependencyInjection\Container;

class EntityReferenceTargetFieldFieldGenerator extends EntityReferenceFieldGenerator {
  use UnionGenerator;
  /**
   * @var array[]
   */
  protected $field_target_identifiers;

  public function __construct(FieldTypePluginManagerInterface $fieldTypePluginManager, EntityTypeManagerInterface $entityTypeManager, $field_target_identifiers) {
    parent::__construct($fieldTypePluginManager, $entityTypeManager);
    $this->field_target_identifiers = $field_target_identifiers;
  }

  public function supportsGeneration($object) {
    if (!parent::supportsGeneration($object)) {
      return FALSE;
    }

    return !!$this->getFieldData($object);
  }

  protected function getFieldData($object) {
    if (isset($this->field_target_identifiers[$object->getSettings()['target_type']])) {
      return [
        '',
        $this->field_target_identifiers[$object->getSettings()['target_type']]
      ];
    }

    if (isset($this->field_target_identifiers[$object->getTargetEntityTypeId() . '-' . $object->getName()])) {
      return [
        Container::camelize($object->getTargetEntityTypeId()) . Container::camelize($object->getName()),
        $this->field_target_identifiers[$object->getTargetEntityTypeId() . '-' . $object->getName()]
      ];
    }

    if ($object->getTargetBundle() && isset($this->field_target_identifiers[$object->getTargetEntityTypeId() . '-' . $object->getTargetBundle() . '-' . $object->getName()])) {
      return [
        Container::camelize($object->getTargetEntityTypeId()) . Container::camelize($object->getTargetBundle()) .  Container::camelize($object->getName()),
        $this->field_target_identifiers[$object->getTargetEntityTypeId() . '-' . $object->getTargetBundle() . '-' . $object->getName()]
      ];
    }

    return FALSE;
  }

  protected function getItemProperties($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */
    $storage_object = $object->getFieldStorageDefinition();

    $properties = parent::getItemProperties($object, $settings, $result, $component_result);

    $properties['target'] = $component_result->getContext('target');

//    list(, $field_identifiers) = $this->getFieldData($object);
//    foreach ($field_identifiers as $field_names) {
//      $properties[$field_names] = 'string'; // $this->generator->generate($object, $settings, $result, $component_result);
//    }


    return $properties;
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $mapping = parent::getItemMapping($object, $properties, $settings, $result, $componentResult);

    $mapping['target'] = 'target';

//    $field_identifiers = array_values($this->field_target_identifiers)[0];
//    foreach($field_identifiers as $field) {
//      $mapping[$field] = $field;
//    }
    return $mapping;
  }

  protected function getName($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */
    // $entity_type = $this->entityTypeManager->getDefinition($object->getSettings()['target_type']);

    $name = parent::getName($object, $settings, $result, $component_result);
    list($name_suffix, ) = $this->getFieldData($object);
    return $name . $name_suffix;

    // return $name . Container::camelize($object->getTargetEntityTypeId()) . Container::camelize($object->getName());


//    if (!($object instanceof FieldConfigInterface)) {
//      return $name;
//    }
//
//    $handler = $object->getSetting('handler');
//    if ($handler !== 'default:' . $entity_type->id()) {
//      return $name;
//    }
//
//    $handler_settings = $object->getSetting('handler_settings');
//    if (empty($handler_settings) || empty($handler_settings['target_bundles'])) {
//      return $name;
//    }
//
//    return $name . Container::camelize($object->getTargetEntityTypeId()) . Container::camelize($object->getTargetBundle()) . Container::camelize($object->getName());
  }

  public function getBundles($object) {
    if (!($object instanceof FieldConfigInterface)) {
      return NULL;
    }

    $entity_type_id = $object->getSettings()['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    if ($bundle_entity_type_id = $entity_type->getBundleEntityType()) {
      $bundles = [];
      foreach ($this->entityTypeManager->getStorage($bundle_entity_type_id)->loadMultiple() as $entity) {
        $bundles[] = $entity;
      }
    } else {
      return NULL;
    }

    $handler = $object->getSetting('handler');
    if ($handler !== 'default:' . $entity_type->id()) {
      return $bundles;
    }

    $handler_settings = $object->getSetting('handler_settings');
    if (empty($handler_settings) || empty($handler_settings['target_bundles'])) {
      return $bundles;
    }

    $negate = !empty($handler_settings['negate']);
    $target_bundle_names = array_values(array_filter($handler_settings['target_bundles']));

    $result_bundles = [];
    foreach ($bundles as $bundle) {
      if ($negate xor in_array($bundle->id(), $target_bundle_names)) {
        $result_bundles[] = $bundle;
      }
    }

    return $result_bundles;
  }

  public function preGenerateBundles($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $bundle_list = $this->getBundles($object);
    if (!$bundle_list) {
      return FALSE;
    }

    $bundles = [];
    foreach ($bundle_list as $bundle) {
      $name = $this->getName($object, $settings, $result, $componentResult) . 'Target' . Container::camelize($bundle->id());
      $bundles[$bundle->id()] = new ComponentResult([
        'type' => ':types/' . $name . ':',
        'target_type' => ':types/Parsed' . $name . ':',
        'parser' => ':parser/' . Container::underscore($name) . '_parser:',
        'guard' => ':parser/' . Container::underscore($name) . '_guard:',
      ]);
    }
    return $bundles;
  }

  public function generateTarget($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $name = $this->getName($object, $settings, $result, $componentResult) . 'Target';

    $bundles = $this->getBundles($object, $settings, $result, $componentResult);
    if ($bundles) {
      $targetComponentResult = new ComponentResult();
      $bundles_results = $componentResult->getContext('bundles');

      $type = $targetComponentResult->setComponent('type', $result->setComponent(
        'types/' . $name,
        'type ' . $name . " = " . $this->generateUnionObject($bundles_results, 'type')
      ));
      if ($settings->generateParser()) {
        $target_type = $targetComponentResult->setComponent('target_type', $result->setComponent(
          'types/Parsed' . $name,
          'type Parsed' . $name . " = " . $this->generateUnionObject($bundles_results, 'target_type')
        ));
        $targetComponentResult->setComponent('parser', $result->setComponent(
          'parser/' . Container::underscore($name) . '_parser',
          'const ' . Container::underscore($name) . '_parser' . ' = ' . $this->generateUnionParser(
            $bundles_results,
            $type,
            $target_type
          )
        ));
      }

      return $targetComponentResult;

    } else {

    }


  }

  protected function preGenerate($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $bundles = $componentResult->getContext('bundles');
    if (!isset($bundles)) {
      $bundles = $this->preGenerateBundles($object, $settings, $result, $componentResult);
      $componentResult->setContext('bundles', $bundles);
//      $bundles = $this->generateBundles($object, $settings, $result, $componentResult);
//      $componentResult->setContext('bundles', $bundles);
    }

    $target = $componentResult->getContext('target');
    if (!$target) {
      $target = $this->generateTarget($object, $settings, $result, $componentResult);
      $componentResult->setContext('target', $target);
    }

    parent::preGenerate($object, $settings, $result, $componentResult);
  }
}
