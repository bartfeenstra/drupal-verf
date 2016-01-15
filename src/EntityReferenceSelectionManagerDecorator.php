<?php

/**
 * Contains \Drupal\verf\DefaultConfigurationPluginManagerDecorator.
 */

namespace Drupal\verf;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\plugin\PluginManager\PluginManagerDecorator;

/**
 * Provides an entity reference selection manager decorator.
 */
class EntityReferenceSelectionManagerDecorator extends PluginManagerDecorator {

  /**
   * The target entity type ID.
   *
   * @var string
   */
  protected $targetEntityTypeId;

  /**
   * Creates a new instance.
   *
   * @param string $target_entity_type_id
   *   The ID of the entity type that is to be referenced.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager
   *   The decorated plugin manager.
   * @param \Drupal\Component\Plugin\Discovery\DiscoveryInterface|null $discovery
   *   A plugin discovery to use instead of the decorated plugin manager, or
   *   NULL to use the decorated plugin manager.
   */
  public function __construct($target_entity_type_id, PluginManagerInterface $plugin_manager, DiscoveryInterface $discovery = NULL) {
    parent::__construct($plugin_manager, $discovery);
    $this->targetEntityTypeId = $target_entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  protected function processDecoratedDefinitions(array $decorated_definitions) {
    return array_filter($decorated_definitions, function($decorated_definition) {
      return is_array($decorated_definition['entity_types']) && ($decorated_definition['entity_types'] === [] || in_array($this->targetEntityTypeId, $decorated_definition['entity_types']));
    });
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    // Normally we must never interact with plugins' internal configuration,
    // but Drupal core's entity reference selection plugins require such
    // interaction, as they fail to provide APIs for setting required
    // configuration. See https://www.drupal.org/node/2636322.
    $default_configuration = [
      'target_type' => $this->targetEntityTypeId,
      'handler_settings' => [
        'sort' => [
          'direction' => 'ASC',
          'field' => NULL,
        ],
        'target_bundles' => [],
      ],
    ];
    $configuration = NestedArray::mergeDeep($default_configuration, $configuration);

    return parent::createInstance($plugin_id, $configuration);
  }

}
