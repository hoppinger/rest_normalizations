<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

class LinkItemNormalizer extends FieldItemNormalizer {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = LinkItem::class;

  public function normalize($field_item, $format = NULL, array $context = []) {
    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $field_item */
    $values = parent::normalize($field_item, $format, $context);
    $values['processed_url'] = $field_item->getUrl()->toString();

    return $values;
  }
}