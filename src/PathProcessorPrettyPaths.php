<?php

namespace Drupal\facets_pretty_paths;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for facets_pretty_paths.
 */
class PathProcessorPrettyPaths implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {

    // TODO: check if we can do this faster, now every url requires a load of all facet sources once (its cached later though)
    $facet_source_plugin_manager = \Drupal::service('plugin.manager.facets.facet_source');
    $facet_sources = $facet_source_plugin_manager->getDefinitions();

    // If path starts with an url having a facet source, reroute all subpaths to
    // the facet source.
    foreach ($facet_sources as $facet_source) {
      $facet_source_plugin = $facet_source_plugin_manager->createInstance($facet_source['id']);
      $facet_source_path = $facet_source_plugin->getPath();
      if ($path && strpos($path, $facet_source_path, 0) === 0) {
        $path = $facet_source_path;
      }
    }

    return $path;
  }

}
