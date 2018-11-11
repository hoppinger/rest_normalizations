<?php

namespace Drupal\rest_normalizations;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class RestNormalizationsServiceProvider extends ServiceProviderBase {
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('redirect.destination');
    $definition->setClass('Drupal\rest_normalizations\RedirectDestination');
  }
}