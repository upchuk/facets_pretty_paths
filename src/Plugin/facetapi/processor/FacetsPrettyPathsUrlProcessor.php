<?php

/**
 * @file
 * Contains Drupal\facets_pretty_paths\Plugin\facetapi\url_processor\FacetsPrettyPathsUrlProcessor.
 */

namespace Drupal\facets_pretty_paths\Plugin\facetapi\processor;

use Drupal\Core\Url;
use Drupal\facetapi\FacetInterface;
use Drupal\facetapi\Processor\UrlProcessorPluginBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @FacetApiProcessor(
 *   id = "facets_pretty_paths",
 *   label = @Translation("Pretty paths url processor"),
 *   description = @Translation("Pretty paths url processor."),
 *   stages = {
 *     "pre_query" = 50,
 *     "build" = 15,
 *   },
 * )
 */
class FacetsPrettyPathsUrlProcessor extends UrlProcessorPluginBase {

  /**
   * A string that separates the filters in the query string.
   */
  const SEPARATOR = ':';

  /**
   * @var array
   *   An array containing the active filters
   */
  protected $active_filters = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request);
    $this->initializeActiveFilters();
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {

    // No results are found for this facet, so don't try to create urls.
    if (empty($results)) {
      return [];
    }

    $facet_source_path = '/search/content';

    $path = $this->request->getPathInfo();
    $filters = substr($path, (strlen($facet_source_path)));


    /** @var \Drupal\facetapi\Result\ResultInterface $result */
    foreach ($results as &$result) {
      $filters_current_result = $filters;
      $filter_key = $facet->getFieldAlias();
      // If the value is active, remove the filter string from the parameters.
      if ($result->isActive()) {
        $filters_current_result = str_replace('/' . $filter_key . '/' . $result->getRawValue(), '', $filters_current_result);
      }
      // If the value is not active, add the filter string.
      else {
        $filters_current_result .= '/' . $filter_key . '/' . $result->getRawValue();
      }

//      if ($facet->getPath()) {
//        $request = Request::create('/' . $facet->getPath());
//      }
      $url = Url::fromUri('base:/search/content' . $filters_current_result);
      $result->setUrl($url);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery(FacetInterface $facet) {
    // Get the filter key of the facet.
    if (isset($this->active_filters[$facet->getFieldAlias()])) {
      foreach ($this->active_filters[$facet->getFieldAlias()] as $value) {
        $facet->setActiveItem(trim($value, '"'));
      }
    }
  }

  /**
   * Initialize the active filters.
   *
   * Get all the filters that are active. This method only get's all the
   * filters but doesn't assign them to facets. In the processFacet method the
   * active values for a specific facet are added to the facet.
   */
  protected function initializeActiveFilters() {
    $facet_source_path = '/search/content';

    $path = $this->request->getPathInfo();
    if(strpos($path, $facet_source_path, 0)=== 0){
      $filters = substr($path, (strlen($facet_source_path) + 1));
      $parts = explode('/', $filters);
      $key = '';
      foreach($parts as $index => $part){
        if($index%2 == 0){
          $key = $part;
        }else{
          if (!isset($this->active_filters[$key])) {
            $this->active_filters[$key] = [$part];
          }
          else {
            $this->active_filters[$key][] = $part;
          }
        }

      }
    }
  }

}
