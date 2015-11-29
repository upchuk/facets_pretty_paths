<?php

/**
 * @file
 * Contains \Drupal\url_alter_test\PathProcessorTest.
 */

namespace Drupal\facets_pretty_paths;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for url_alter_test.
 */
class PathProcessorPrettyPaths implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

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

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = array(), Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {

    // If path is a facet source, alter it to have subpaths for the given queries.
    if($path == '/search/content'){
      foreach($options['query']['f'] as $facetquery){
        $parts = explode(':', $facetquery);
        $key = $parts[0];
        $value = $parts[1];
        $path .= '/' . $key . '/' . $value;
      }
    }
    return $path;
  }

}
