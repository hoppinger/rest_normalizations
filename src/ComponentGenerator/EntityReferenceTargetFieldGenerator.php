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

class EntityReferenceTargetFieldGenerator extends EntityReferenceFieldGenerator {
  use UnionGenerator;

  protected $needsItemGuard = true;

  /**
   * @var string[]
   */
  protected $target_identifiers;

  public function __construct(FieldTypePluginManagerInterface $fieldTypePluginManager, EntityTypeManagerInterface $entityTypeManager, $target_identifiers) {
    parent::__construct($fieldTypePluginManager, $entityTypeManager);
    $this->target_identifiers = $target_identifiers;
  }

  public function supportsGeneration($object) {
    if (!parent::supportsGeneration($object)) {
      return FALSE;
    }

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */

    $field_entity_identifiers = array_filter([
      $object->getSettings()['target_type'],
      $object->getTargetEntityTypeId() . '-' . $object->getName(),
      $object->getTargetBundle() ? (
        $object->getTargetEntityTypeId() . '-' . $object->getTargetBundle() . '-' . $object->getName()
      ) : NULL,
    ]);

    return !!array_intersect($field_entity_identifiers, $this->target_identifiers);
  }

  protected function getName($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */
    $entity_type = $this->entityTypeManager->getDefinition($object->getSettings()['target_type']);

    $name = parent::getName($object, $settings, $result, $component_result) . 'WithTarget';

    if (!($object instanceof FieldConfigInterface)) {
      return $name;
    }

    $handler = $object->getSetting('handler');
    if ($handler !== 'default:' . $entity_type->id()) {
      return $name;
    }

    $handler_settings = $object->getSetting('handler_settings');
    if (empty($handler_settings) || empty($handler_settings['target_bundles'])) {
      return $name;
    }

    return $name . Container::camelize($object->getTargetEntityTypeId()) . Container::camelize($object->getTargetBundle()) . Container::camelize($object->getName());
  }

  protected function getItemProperties($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */

    $properties = parent::getItemProperties($object, $settings, $result, $component_result);

    $properties['target?'] = $this->getTargetProperty($object, $settings, $result, $component_result);

    return $properties;
  }

  protected function getTargetProperty($object, Settings $settings, Result $result, ComponentResult $component_result) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */
    $entity_type = $this->entityTypeManager->getDefinition($object->getSettings()['target_type']);
    $entityComponentResult = $this->generator->generate($entity_type, $settings, $result);

    if (!($object instanceof FieldConfigInterface)) {
      return $entityComponentResult;
    }

    $handler = $object->getSetting('handler');
    if ($handler !== 'default:' . $entity_type->id()) {
      return $entityComponentResult;
    }

    $handler_settings = $object->getSetting('handler_settings');
    if (empty($handler_settings) || empty($handler_settings['target_bundles'])) {
      return $entityComponentResult;
    }

    $negate = !empty($handler_settings['negate']);
    $target_bundle_names = array_values(array_filter($handler_settings['target_bundles']));

    $target_bundles = [];
    $bundles = $entityComponentResult->getContext('bundles');

    if (!$negate) {
      foreach ($target_bundle_names as $bundle_key) {
        if (empty($bundles[$bundle_key])) {
          return $entityComponentResult;
        }

        $target_bundles[] = $bundles[$bundle_key];
      }
    } else {
      foreach (array_keys($bundles) as $bundle_key => $bundle) {
        if (!in_array($bundle_key, $target_bundle_names)) {
          $target_bundles[] = $bundle;
        }
      }
    }

    $type = $this->generateUnionObject($target_bundles, 'type', TRUE);
    $target_type = $this->generateUnionObject($target_bundles, 'target_type', TRUE);

    return new ComponentResult([
      'type' => $type,
      'target_type' => $target_type,
      'parser' => '(' . $this->generateUnionParser($target_bundles, $type, $target_type) . ')'
    ]);
  }

  public function getItemMapping($object, $properties, Settings $settings, Result $result, ComponentResult $componentResult) {
    return 'target';
  }

  public function generateTargetType($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $object */

    if ($object->getFieldStorageDefinition()->getCardinality() == 1) {
      return parent::generateTargetType($object, $settings, $result, $componentResult);
    }

    $item_target_type = $componentResult->getContext('item')->getComponent('target_type');
    $item_target_type = implode(' | ', array_diff(array_map('trim', explode('|', $item_target_type)), array('undefined')));

    return ':/immutable/List:<' . $item_target_type . '>';
  }

  public function generateParser($object, Settings $settings, Result $result, ComponentResult $componentResult) {
    $type = $this->generateType($object, $settings, $result, $componentResult);
    $target_type = $this->generateTargetType($object, $settings, $result, $componentResult);
    $target_type = $this->cleanupPropertyType($target_type);

    $item_target_type = $componentResult->getContext('item')->getComponent('target_type');
    $item_target_type = implode(' | ', array_diff(array_map('trim', explode('|', $item_target_type)), array('undefined')));

    $item_properties = $this->getItemProperties($object, $settings, $result, $componentResult);
    $item_mapping = $this->getItemMapping($object, $properties, $settings, $result, $componentResult);

    $item_parser = $componentResult->getContext('item')->getComponent('parser');
    $item_guard = $componentResult->getContext('item')->getComponent('guard');
    
    if ($object->getFieldStorageDefinition()->getCardinality() == 1) {
      if ($object->isRequired()) {
        $name = 'singular_required_' . Container::underscore($this->getName($object, $settings, $result, $componentResult)) . '_parser';

        if (is_string($item_mapping) && substr($item_mapping, -1) == '?') {
          return $result->setComponent('parser/' . $name, 'const ' . $name . ' = (f: ' . $type . '): ' . $target_type . ' => (f[0] !== undefined ? ' . $item_parser . '(f[0]) : undefined)');
        } else {
          return $result->setComponent('parser/' . $name, 'const ' . $name . ' = (f: ' . $type . '): ' . $target_type . ' => ' . $item_parser . '(f[0])');
        }
      } else {
        $name = 'singular_optional_' . Container::underscore($this->getName($object, $settings, $result, $componentResult)) . '_parser';
        return $result->setComponent('parser/' . $name, 'const ' . $name . ' = (f: ' . $type . '): ' . $target_type . ' => f && f.length > 0' . ($item_guard ? ' && ' .  $item_guard . '(f[0])' : '') . ' ? ' . $item_parser . '(f[0]) : null');
      }
    }

    $name = 'plural_' . Container::underscore($this->getName($object, $settings, $result, $componentResult)) . '_parser';
    return $result->setComponent('parser/' . $name, 'const ' . $name . " =\n  (f: " . $type . '): ' . $target_type . " =>\n    :/immutable/List:<" . $item_target_type . '>(f' . ($item_guard ? '.filter(i => ' .  $item_guard . '(i))' : '') . '.map(i => ' . $item_parser . '(i)).filter(i => i !== undefined))');
  }
}
