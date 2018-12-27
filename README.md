# Rest Normalizations

Normalizers are one of the key parts of Drupal 8 Serialization API. They help to interact with the Drupal core REST API. Serialization consists of normalization and an encoding step, in the chosen format.

## Installation

```sh
composer require hoppinger/rest_normalizations
```

## Usage
Rest Normalization module helps to embed the entity referenced in the content, by altering the REST API. 

For example, The Entity reference field in the REST Output provides the target_id, target_type and URI. In some cases, we need more data regarding the referenced entity other than the provided data. Rest Normalization helps to solve this problem by overriding the default values and embedding the entity data into the response.

Create a .php file in the module directory <directory>/src with the following content. Replace the `<target_identifier>` with the project-specific entity reference field that needs to be embedded into the API response (for example: `node-story_overview-field_featured_story`)

The <target_identifiers> can be entityreference fields, Paragraphs, Image fields, Video fields, Media field, Taxonomyreference field. 

The filename must be similar to the module name i.e., ModuleNameServiceProvider.php and must be placed in the src folder.

```php
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class AlterServiceProvider extends ServiceProviderBase {
  public function alter(ContainerBuilder $container) {
    if ($container->hasParameter('rest_normalizations.target_identifiers')) {
      $target_identifiers = $container->getParameter('rest_normalizations.target_identifiers');
      
      $target_identifiers[] = <target_identifier>; #e.g., node-story_overview-field_featured_story
  
      $container->setParameter('rest_normalizations.target_identifiers', $target_identifiers);
  }
}
```

By default, media, file and paragraph are already added as the target identifiers.
