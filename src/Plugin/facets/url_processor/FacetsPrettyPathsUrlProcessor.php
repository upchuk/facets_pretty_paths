<?php

namespace Drupal\facets_pretty_paths\Plugin\facets\url_processor;

use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pretty paths URL processor.
 *
 * @FacetsUrlProcessor(
 *   id = "facets_pretty_paths",
 *   label = @Translation("Pretty paths"),
 *   description = @Translation("Pretty paths uses slashes as separator, e.g. /brand/drupal/color/blue"),
 * )
 */
class FacetsPrettyPathsUrlProcessor extends UrlProcessorPluginBase {

  /**
   * @var array
   *   An array containing the active filters
   */
  protected $activeFilters = [];

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

    $path = rtrim($this->request->getPathInfo(), '/');
    $filters = substr($path, (strlen($facet->getFacetSource()->getPath())));
    $coder_plugin_manager = \Drupal::service('plugin.manager.facets_pretty_paths.coder');
    $coder_id = $facet->getThirdPartySetting('facets_pretty_paths', 'coder', 'default_coder');
    $coder = $coder_plugin_manager->createInstance($coder_id, ['facet' => $facet]);

    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as &$result) {
      $raw_value = $result->getRawValue();
      $encoded_value = $coder->encode($raw_value);

      $filters_current_result = $filters;
      $filter_key = $facet->getUrlAlias();
      // If the value is active, remove the filter string from the parameters.
      if ($result->isActive()) {
        $filters_current_result = str_replace('/' . $filter_key . '/' . $encoded_value, '', $filters_current_result);
        if ($facet->getEnableParentWhenChildGetsDisabled() && $facet->getUseHierarchy()) {
          // Enable parent id again if exists.
          $parent_ids = $facet->getHierarchyInstance()->getParentIds($raw_value);
          if (isset($parent_ids[0]) && $parent_ids[0]) {
            $filters_current_result .= '/' . $filter_key . '/' . $coder->encode($parent_ids[0]);
          }
        }
      }
      // If the value is not active, add the filter string.
      else {
        $filters_current_result .= '/' . $filter_key . '/' . $encoded_value;

        if ($facet->getUseHierarchy()) {
          // If hierarchy is active, unset parent trail and every child when
          // building the enable-link to ensure those are not enabled anymore.
          $parent_ids = $facet->getHierarchyInstance()->getParentIds($raw_value);
          $child_ids = $facet->getHierarchyInstance()->getNestedChildIds($raw_value);
          $parents_and_child_ids = array_merge($parent_ids, $child_ids);
          foreach ($parents_and_child_ids as $id) {
            $filters_current_result = str_replace('/' . $filter_key . '/' . $coder->encode($id) . '/', '/', $filters_current_result);
          }
        }
        // Exclude currently active results from the filter params if we are in
        // the show_only_one_result mode.
        if ($facet->getShowOnlyOneResult()) {
          foreach ($results as $result2) {
            if ($result2->isActive()) {
              $active_filter_string = '/' . $filter_key . '/' . $coder->encode($result2->getRawValue());
              $filters_current_result = str_replace($active_filter_string, '', $filters_current_result);
            }
          }
        }
      }

      $url = Url::fromUri('base:' . $facet->getFacetSource()->getPath() . $filters_current_result);

      // First get the current list of get parameters.
      $get_params = $this->request->query;
      // When adding/removing a filter the number of pages may have changed,
      // possibly resulting in an invalid page parameter.
      if ($get_params->has('page')) {
        $current_page = $get_params->get('page');
        $get_params->remove('page');
      }
      $url->setOption('query', $get_params->all());
      $result->setUrl($url);
      // Restore page parameter again. See https://www.drupal.org/node/2726455.
      if (isset($current_page)) {
       $get_params->set('page', $current_page);
      }
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveItems(FacetInterface $facet) {
    // Get the filter key of the facet.
    if (isset($this->activeFilters[$facet->getUrlAlias()])) {
      $coder_plugin_manager = \Drupal::service('plugin.manager.facets_pretty_paths.coder');
      $coder_id = $facet->getThirdPartySetting('facets_pretty_paths', 'coder', 'default_coder');
      $coder = $coder_plugin_manager->createInstance($coder_id, ['facet' => $facet]);

      foreach ($this->activeFilters[$facet->getUrlAlias()] as $value) {
        $facet->setActiveItem(trim($coder->decode($value), '"'));
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
    if ($configuration['facet']) {
      $facet_source_path = $configuration['facet']->getFacetSource()->getPath();
    }

    $path = $this->request->getPathInfo();
    if (strpos($path, $facet_source_path, 0) === 0) {
      $filters = substr($path, (strlen($facet_source_path) + 1));
      $parts = explode('/', $filters);
      $key = '';
      foreach ($parts as $index => $part) {
        if ($index % 2 == 0) {
          $key = $part;
        }
        else {
          if (!isset($this->activeFilters[$key])) {
            $this->activeFilters[$key] = [$part];
          }
          else {
            $this->activeFilters[$key][] = $part;
          }
        }
      }
    }
  }

}
