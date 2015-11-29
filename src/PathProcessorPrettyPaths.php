<?php

/**
 * @file
 * Contains \Drupal\url_alter_test\PathProcessorTest.
 */

namespace Drupal\facets_pretty_paths;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for url_alter_test.
 */
class PathProcessorPrettyPaths implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {

    // If path starts with an url having a facet source, reroute all subpaths to
    // the facet source.
    // Path example: /search/content/entity_node_field_tags_entity_name/swi
    if(strpos($path, '/search/content', 0)=== 0){
      $path = '/search/content';
    }
    return $path;
  }
}
