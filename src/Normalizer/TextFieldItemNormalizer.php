<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\text\Plugin\Field\FieldType\TextItemBase;

class TextFieldItemNormalizer extends FieldItemNormalizer {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = TextItemBase::class;

  public function normalize($field_item, $format = NULL, array $context = []): \ArrayObject|array|string|int|float|bool|null {
    /** @var \Drupal\text\Plugin\Field\FieldType\TextItemBase $field_item */
    $values = parent::normalize($field_item, $format, $context);
    $values['processed'] = $this->serializer->normalize($field_item->get('processed'), $format, $context);

    return $values;
  }
}