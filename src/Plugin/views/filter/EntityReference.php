<?php

/**
 * @file
 * Contains \Drupal\verf\Plugin\views\filter\InOperator.
 */

namespace Drupal\verf\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface;
use Drupal\plugin\PluginType\PluginTypeInterface;
use Drupal\verf\EntityReferenceSelectionManagerDecorator;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Views filter for entity reference fields.
 *
 * This class has hardcoded dependencies on Drupal core's entity reference
 * selection plugins, and
 * \Drupal\plugin\Plugin\Plugin\PluginSelector\SelectList. The reason for this
 * is that the entity reference selection plugins do not expose their internal
 * configuration, and their handler settings must be retrieved from the form
 * state.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("verf")
 */
class EntityReference extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * The plugin selector manager.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface
   */
  protected $pluginSelectorManager;

  /**
   * The entity reference selection plugin type.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeInterface
   */
  protected $entityReferenceSelectionPluginType;

  /**
   * The target entity type ID.
   *
   * @var string
   */
  protected $targetEntityTypeId;

  /**
   * Constructs a new instance.
   *
   * @param mixed[] $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed[] $plugin_definition
   *   The plugin definition.
   * @param \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface $plugin_selector_manager
   *   The plugin selector manager.
   * @param \Drupal\plugin\PluginType\PluginTypeInterface $entity_reference_selection_plugin_type
   *   The entity reference selection plugin type.
   * @param string $target_entity_type_id
   *   The ID of the entity reference's target entity type.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PluginSelectorManagerInterface $plugin_selector_manager, PluginTypeInterface $entity_reference_selection_plugin_type, $target_entity_type_id) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityReferenceSelectionPluginType = $entity_reference_selection_plugin_type;
    $this->pluginSelectorManager = $plugin_selector_manager;
    $this->targetEntityTypeId = $target_entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\plugin\PluginType\PluginTypeManagerInterface $plugin_type_manager */
    $plugin_type_manager = $container->get('plugin.plugin_type_manager');

    return new static($configuration, $plugin_id, $plugin_definition, $container->get('plugin.manager.plugin.plugin_selector'), $plugin_type_manager->getPluginType('entity_reference_selector'), $configuration['verf_target_entity_type_id']);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // We use the "default" selection by default. Its derivative IDs are entity
    // type IDs.
    $options['entity_reference_selection_id'] = [
      'default' => 'default:' . $this->targetEntityTypeId,
    ];
    $options['entity_reference_selection_handler_settings'] = [
      'default' => [],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $entity_reference_selection_selector = $this->getPluginSelector($form_state);
    $form['entity_reference_selection'] = $entity_reference_selection_selector->buildSelectorForm([], $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);
    $entity_reference_selection_selector = $this->getPluginSelector($form_state);
    $entity_reference_selection_selector->validateSelectorForm($form['entity_reference_selection'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $entity_reference_selection_selector = $this->getPluginSelector($form_state);
    $entity_reference_selection_selector->submitSelectorForm($form['entity_reference_selection'], $form_state);
    // Views magically sets all submitted form values as plugin options. We must
    // therefore unset any values submitted by the entity reference selection
    // plugin form, and add the ID and configuration of the selected plugin.
    $entity_reference_selection = $entity_reference_selection_selector->getSelectedPlugin();
    $form_state->setValue(['options', 'entity_reference_selection_id'], $entity_reference_selection->getPluginId());
    $entity_reference_selection_handler_settings = $form_state->getValue(['options', 'entity_reference_selection', 'container', 'plugin_form']);
    $form_state->setValue(['options', 'entity_reference_selection_handler_settings'], $entity_reference_selection_handler_settings);
    $form_state->unsetValue(['options', 'entity_reference_selection']);
  }

  /**
   * Gets the plugin selector.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface
   */
  protected function getPluginSelector(FormStateInterface $form_state) {
    if ($form_state->has('plugin_selector')) {
      return $form_state->get('plugin_selector');
    }

    $plugin_selector = $this->pluginSelectorManager->createInstance('plugin_select_list');
    $plugin_selector->setSelectablePluginType($this->entityReferenceSelectionPluginType);
    $entity_reference_selection_manager = $this->getPluginManager();
    $plugin_selector->setSelectablePluginDiscovery($entity_reference_selection_manager);
    $plugin_selector->setSelectablePluginFactory($entity_reference_selection_manager);
    $selected_entity_reference_selection = $entity_reference_selection_manager->createInstance($this->options['entity_reference_selection_id'], $this->options['entity_reference_selection_handler_settings']);
    $plugin_selector->setSelectedPlugin($selected_entity_reference_selection);
    $plugin_selector->setRequired();
    $plugin_selector->setLabel($this->t('Selection method'));
    $form_state->set('plugin_selector', $plugin_selector);

    return $plugin_selector;
  }

  /**
   * Gets the entity reference selection manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected function getPluginManager() {
    return new EntityReferenceSelectionManagerDecorator($this->targetEntityTypeId, $this->entityReferenceSelectionPluginType->getPluginManager());;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!is_null($this->valueOptions)) {
      return $this->valueOptions;
    }

    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $entity_reference_selection */
    $entity_reference_selection = $this->getPluginManager()->createInstance($this->options['entity_reference_selection_id'], $this->options['entity_reference_selection_handler_settings']);
    $this->valueOptions = [];
    foreach ($entity_reference_selection->getReferenceableEntities() as $bundle_options) {
      $this->valueOptions = array_merge($this->valueOptions, $bundle_options);
    }
    natcasesort($this->valueOptions);

    return $this->valueOptions;
  }

}
