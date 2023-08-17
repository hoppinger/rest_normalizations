<?php 

namespace Drupal\rest_normalizations_video_embed_field\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;
use Drupal\video_embed_field\Plugin\Field\FieldType\VideoEmbedField;
use Drupal\video_embed_field\ProviderManagerInterface;


class VideoEmbedFieldNormalizer extends FieldItemNormalizer {
  /**
   * The embed provider plugin manager.
   *
   * @var \Drupal\video_embed_field\ProviderManagerInterface
   */
  protected $providerManager;

  protected $supportedInterfaceOrClass = VideoEmbedField::class;

  public function __construct(ProviderManagerInterface $provider_manager) {
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): \ArrayObject|array|string|int|float|bool|null  {
    $data = parent::normalize($object, $format, $context);

    $provider = $this->providerManager->loadProviderFromInput($object->value);
    if (!$provider) {
      return $data;
    }

    $provider_id = $provider->getPluginId();

    $provider_class = get_class($provider);
    $id = call_user_func(array($provider_class, 'getIdFromInput'), $object->value);

    $data['video_type'] = $provider_id;
    $data['video_id'] = $id;

    $embed_code = $provider->renderEmbedCode(0, 0, FALSE);
    $url = $this->buildUrlFromEmbedCode($embed_code);
    $data['iframe_url'] = $url;
    
    return $data;
  }

  protected function buildUrlFromEmbedCode($embed_code) {
    $url = $embed_code['#url'];

    if (!empty($embed_code['#query'])) {
      if (is_array($embed_code['#query'])) {
        if (defined('PHP_QUERY_RFC3986')) {
          $url .= '?' . http_build_query($embed_code['#query'], '', '&', PHP_QUERY_RFC3986);
        } else {
          $url .= '?' .  http_build_query($embed_code['#query'], '', '&');
        }
      } else {
        $url .= '?' . rawurlencode($embed_code['#query']);
      }
    }

    if (!empty($embed_code['#fragment'])) {
      $url .= '#' . $embed_code['#fragment'];
    }

    return $url;
  }
}