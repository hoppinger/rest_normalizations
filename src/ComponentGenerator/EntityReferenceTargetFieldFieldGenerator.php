<?php

namespace Drupal\rest_normalizations\ComponentGenerator;

use Drupal\Core\Entity\EntityFieldManagerInterface;
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

  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  public function __construct(FieldTypePluginManagerInterface $fieldTypePluginManager, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, $field_target_identifiers) {
    parent::__construct($fieldTypePluginManager, $entityTypeManager);

    $this->entityFieldManager = $entityFieldManager;
    $this->field_target_identifiers = $field_target_identifiers;
  }

  public function supportsGeneration($object) {
    if (!parent::supportsGeneration($object)) {
      return FALSE;
    }

    return !!$this->getFieldData($object);
  }

  /**
   * Generate name suffix and field target identifiers.
   *
   * @param $object
   * @return [string, array]|bool
   */
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

    return $properties;
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    $mapping = parent::getItemMapping($object, $properties, $settings, $result, $componentResult);

    $mapping['target'] = 'target';

    return $mapping;
  }

  protected function getName($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */
    $name = parent::getName($object, $settings, $result, $component_result);
    list($name_suffix, ) = $this->getFieldData($object);
    return $name . $name_suffix;
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

  public function generateTargetFromBundles($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $name = $this->getName($object, $settings, $result, $componentResult) . 'Target';

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
  }

  public function preGenerateTargetWithoutBundles($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $name = $this->getName($object, $settings, $result, $componentResult) . 'Target';

    return new ComponentResult([
      'type' => ':types/' . $name . ':',
      'target_type' => ':types/Parsed' . $name . ':',
      'parser' => ':parser/' . Container::underscore($name) . '_parser:'
    ]);
  }

  public function generateTargetWithoutBundles($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $name = $this->getName($object, $settings, $result, $componentResult) . 'Target';
    list(, $field_target_identifiers) = $this->getFieldData($object);

    $entity_type_id = $object->getSettings()['target_type'];
    $base_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);

    $properties = [];
    $mapping = [];

    foreach ($base_field_definitions as $key => $field_definition) {
      if ($field_definition->isInternal()) {
        continue;
      }

      if (!in_array($key, $field_target_identifiers)) {
        continue;
      }

      $property_value = $this->generator->generate($field_definition, $settings, $result);
      $properties[$key] = $property_value;
      $mapping[$key] = $key;
    }

    return $this->generatePropertiesComponentResult(
      $properties,
      $name,
      'Parsed' . $name,
      Container::underscore($name) . '_parser',
      $mapping,
      $settings,
      $result
    );
  }

    /**
   * @param $object
   * @param Settings $settings
   * @param Result $result
   * @param ComponentResult $componentResult
   * @return array|bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function generateBundles($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $bundle_list = $this->getBundles($object);
    if (!$bundle_list) {
      return FALSE;
    }

    $bundles = [];
    foreach ($bundle_list as $bundle) {
      $bundles[$bundle->id()] = $this->generateBundle($object, $bundle, $settings, $result, $componentResult);
    }
    return $bundles;
  }

  /**
   * @param $object
   * @param $bundle
   * @param Settings $settings
   * @param Result $result
   * @param ComponentResult $componentResult
   *
   */
  public function generateBundle($object, $bundle, Settings $settings, Result $result, ComponentResult $componentResult) {
    list(, $field_target_identifiers) = $this->getFieldData($object);

    $entity_type_id = $object->getSettings()['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    $bundle_id = $bundle->id();

    $base_field_definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id);

    $_bundle_field_type = $this->generator->generate($base_field_definitions[$entity_type->getKey('bundle')], $settings, $result);
    $bundle_field_type = $_bundle_field_type->getComponent('wrapper_type') . '<' . $_bundle_field_type->getComponent('specific_item_type') . "<" . json_encode($bundle_id) . ">>";

    $properties = [
      $entity_type->getKey('bundle') => new ComponentResult([
        'type' => $bundle_field_type,
        'target_type' => json_encode($bundle_id),
        'parser' => '((_: any): ' . json_encode($bundle_id) . ' => ' . json_encode($bundle_id) . ')',
        'guard' => '((t: any): t is ' . $bundle_field_type . ' => Array.isArray(t) && t[0] !== undefined && t[0].target_id !== undefined && t[0].target_id === ' . json_encode($bundle_id) . ')'
      ])
    ];
    $mapping = [
      'bundle' => $entity_type->getKey('bundle'),
    ];

    foreach ($field_definitions as $key => $field_definition) {
      if ($field_definition->isInternal()) {
        continue;
      }

      if (!in_array($key, $field_target_identifiers)) {
        continue;
      }

      $property_value = $this->generator->generate($field_definition, $settings, $result);
      $properties[$key] = $property_value;
      $mapping[$key] = $key;
    }

    $name = $this->getName($object, $settings, $result, $componentResult) . 'Target' . Container::camelize($bundle->id());

    return $this->generatePropertiesComponentResult(
      $properties,
      $name,
      'Parsed' . $name,
      Container::underscore($name) . '_parser',
      $mapping,
      $settings,
      $result,
      Container::underscore($name) . '_guard'
    );
  }

  protected function preGenerate($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $bundle_list = $this->getBundles($object);
    if ($bundle_list) {
      $bundles = $componentResult->getContext('bundles');
      if (!isset($bundles)) {
        $bundles = $this->preGenerateBundles($object, $settings, $result, $componentResult);
        $componentResult->setContext('bundles', $bundles);
        $bundles = $this->generateBundles($object, $settings, $result, $componentResult);
        $componentResult->setContext('bundles', $bundles);
      }

      $target = $componentResult->getContext('target');
      if (!$target) {
        $target = $this->generateTargetFromBundles($object, $settings, $result, $componentResult);
        $componentResult->setContext('target', $target);
      }
    } else {
      $target = $componentResult->getContext('target');
      if (!$target) {
        $target = $this->preGenerateTargetWithoutBundles($object, $settings, $result, $componentResult);
        $componentResult->setContext('target', $target);
        $target = $this->generateTargetWithoutBundles($object, $settings, $result, $componentResult);
        $componentResult->setContext('target', $target);
      }
    }

    parent::preGenerate($object, $settings, $result, $componentResult);
  }
}
