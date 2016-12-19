<?php

/**
 * @file
 * Contains Drupal\facets_pretty_paths\Plugin\facets\url_processor\FacetsPrettyPathsTaxonomyUrlProcessor.
 */

namespace Drupal\facets_pretty_paths\Plugin\facets\url_processor;

use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\taxonomy\Entity\Term;
use Drupal\facets_pretty_paths\Plugin\facets\url_processor\FacetsPrettyPathsUrlProcessor;

/**
 * Pretty paths URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "facets_pretty_paths_taxonomy",
 *   label = @Translation("Pretty paths taxonomy"),
 *   description = @Translation("Pretty paths that outputs the id, e.g. /alias/term_name-term_id"),
 * )
 */
class FacetsPrettyPathsTaxonomyUrlProcessor extends UrlProcessorPluginBase {

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
    $this->initializeActiveFilters($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {

    // No results are found for this facet, so don't try to create urls.
    if (empty($results)) {
      return [];
    }

    $path = $this->request->getPathInfo();
    $filters = substr($path, (strlen($facet->getFacetSource()->getPath())));

    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as &$result) {
      $filters_current_result = $filters;
      $filter_key = $facet->getUrlAlias();
      $tid = $result->getRawValue();

      if ($term = Term::load($tid)) {
        $term_name = $term->get('name')->value;
        $term_name = \Drupal::service('pathauto.alias_cleaner')->cleanString($term_name);

        $filter_value = '/' . $filter_key . '/' . $term_name . '-' . $tid;
      }
      else {
        $filter_value = $result->getRawValue();
      }

      // If the value is active, remove the filter string from the parameters.
      if ($result->isActive()) {
        $filters_current_result = str_replace($filter_value, '', $filters_current_result);
      }
      // If the value is not active, add the filter string.
      else {
        if ($term = Term::load($tid)) {
          $term_name = $term->get('name')->value;
          $term_name = \Drupal::service('pathauto.alias_cleaner')->cleanString($term_name);

          $filters_current_result .= $filter_value;
        }
        else {
          $filters_current_result .= $filter_value;
        }
      }

      $url = Url::fromUri('base:' . $facet->getFacetSource()->getPath() . $filters_current_result);
      $url->setOption('query', $this->request->query->all());
      $result->setUrl($url);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveItems(FacetInterface $facet) {
    // Get the filter key of the facet.
    if (isset($this->active_filters[$facet->getUrlAlias()])) {
      foreach ($this->active_filters[$facet->getUrlAlias()] as $value) {
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
  protected function initializeActiveFilters($configuration) {
    if ($configuration['facet']){
      $facet_source_path = $configuration['facet']->getFacetSource()->getPath();
    }

    $path = $this->request->getPathInfo();
    if (strpos($path, $facet_source_path, 0)=== 0) {
      $filters = substr($path, (strlen($facet_source_path) + 1));
      $parts = explode('/', $filters);
      $key = '';

      foreach ($parts as $index => $part) {
        if ($index%2 == 0){
          $key = $part;
        }
        else {
          // Taxonomy special case: /alias/term_name-term_id.
          $exploded = explode('-', $part);
          $tid = array_pop($exploded);

          if (!isset($this->active_filters[$key])) {
            $this->active_filters[$key] = [$tid];
          }
          else {
            $this->active_filters[$key][] = $tid;
          }
        }
      }
    }
  }
}
