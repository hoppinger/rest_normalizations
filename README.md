# Rest Normalizations

The Rest Normalizations module for Drupal 8 extends the default Drupal 8 normalizations for the REST API. It adds properties that are helpful in a headless architecture and helps with embedding sub-entities in entities.

## Installation

```sh
composer require hoppinger/rest_normalizations
```

## Version compatibiliy

Version 1.4.0 and up are only compatible with Drupal 8.7.0 and up, and older versions are not compatible with Drupal 8.7.0 and up.
Version 2.0.0 are compatible with Drupal 9.x

## Usage

Rest Normalization module helps to embed the entity referenced in the content, by altering the REST API. 

For example, The Entity reference field in the REST Output provides the target_id, target_type and URI. In some cases, we need more data regarding the referenced entity other than the provided data. Rest Normalization helps to solve this problem by overriding the default values and embedding the entity data into the response.

Create a .php file in the module directory <directory>/src with the following content. Replace the `<target_identifier>` with the project-specific entity reference field that needs to be embedded into the API response (for example: `node-story_overview-field_featured_story`)

The <target_identifiers> can be entityreference fields, Paragraphs, File, Image, Video, Media, Taxonomyreference field. 

The filename must be similar to the module name i.e., ModuleNameServiceProvider.php and must be placed in the src folder.

```php

namespace Drupal\module_name;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class ModuleNameServiceProvider extends ServiceProviderBase {
  public function alter(ContainerBuilder $container) {
    if ($container->hasParameter('rest_normalizations.target_identifiers')) {
      $target_identifiers = $container->getParameter('rest_normalizations.target_identifiers');
      
      $target_identifiers[] = <target_identifier>; #e.g., node-story_overview-field_featured_story
  
      $container->setParameter('rest_normalizations.target_identifiers', $target_identifiers);
  }
}
```

By default, media, file and paragraph are already added as the target identifiers.
