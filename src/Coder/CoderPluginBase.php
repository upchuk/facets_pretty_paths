<?php

namespace Drupal\facets_pretty_paths\Coder;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A base class for plugins that implements most of the boilerplate.
 */
abstract class CoderPluginBase extends ProcessorPluginBase implements CoderInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Symfony\Component\HttpFoundation\Request $request */
    $request = $container->get('request_stack')->getMasterRequest();
    return new static($configuration, $plugin_id, $plugin_definition, $request);
  }

}
