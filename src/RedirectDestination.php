<?php

namespace Drupal\rest_normalizations;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Routing\RedirectDestination as BaseRedirectDestination;

class RedirectDestination extends BaseRedirectDestination {
  public function get() {
    if (!isset($this->destination)) {
      $query = $this->requestStack->getCurrentRequest()->query;

      if ($query->has('destination')) {
        if (UrlHelper::isExternal($query->get('destination'))) {
          $this->destination = '/';
        }
        else {
          $this->destination = $query->get('destination');
        }
      }
      else {
        $this->destination = $this->urlGenerator->generateFromRoute('<current>', [], ['query' => UrlHelper::filterQueryParameters($query->all(), ['_format'])]);
      }
    }

    return $this->destination;
  }
}