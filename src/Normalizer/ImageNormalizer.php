<?php

namespace Drupal\rest_normalizations\Normalizer;

use Drupal\Core\Entity\Entity;
use Drupal\file\Entity\File;
use Drupal;
use Drupal\file\FileInterface;

class ImageNormalizer extends ContentEntityNormalizer {
  protected $supportedInterfaceOrClass = 'Drupal\file\FileInterface';

	public function supportsNormalization($data, ?string $format = NULL, array $context = []): bool {
    if (!parent::supportsNormalization($data, $format, $context)) {
      return FALSE;
    }

    $mimetype = explode('/', $data->getMimeType());
    if ($mimetype[0] != 'image') {
      return FALSE;
    }

    //Prevent styles on gif images
    if ($mimetype[1] == 'gif') {
      return FALSE;
    }

    //Prevent styles on svg images
    if ($mimetype[1] == 'svg+xml') {
      return FALSE;
    }

    return TRUE;
  }

  public function normalize($entity, $format = NULL, array $context = []): \ArrayObject|array|string|int|float|bool|null  {
    $data = parent::normalize($entity, $format, $context);

    $path = $entity->getFileUri();

    $data['style_urls'] = [];
    $styles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
    foreach ($styles as $style) {
      $data['style_urls'][$style->id()] = $style->buildUrl($path);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCacheableSupportsMethod(): bool {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use getSupportedTypes() instead. See https://www.drupal.org/node/3359695', E_USER_DEPRECATED);

    return TRUE;
  }

  public function getSupportedTypes(?string $format): array {
    return [
      FileInterface::class => FALSE,
    ];
  }
}