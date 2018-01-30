<?php

namespace Drupal\facets_pretty_paths\Plugin\facets\url_processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
class FacetsPrettyPathsUrlProcessor extends UrlProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
  * The current_route_match service.
  *
  * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
  */
  protected $routeMatch;

  /**
   * Constructs FacetsPrettyPathsUrlProcessor object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object for the current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager);
    $this->routeMatch = $routeMatch;
    $this->initializeActiveFilters($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getMasterRequest(),
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrls(FacetInterface $facet, array $results) {

    // No results are found for this facet, so don't try to create urls.
    if (empty($results)) {
      return [];
    }

    $initialized_coders = [];
    $initialized_facets = [];
    $filters = $this->getActiveFilters();
    $coder_plugin_manager = \Drupal::service('plugin.manager.facets_pretty_paths.coder');
    $coder_id = $facet->getThirdPartySetting('facets_pretty_paths', 'coder', 'default_coder');
    $coder = $coder_plugin_manager->createInstance($coder_id, ['facet' => $facet]);

    /** @var \Drupal\facets\Result\ResultInterface $result */
    foreach ($results as &$result) {
      $raw_value = $result->getRawValue();

      $filters_current_result = $filters;
      // If the value is active, remove the filter string from the parameters.
      if ($result->isActive()) {
        if (($key = array_search($raw_value, $filters_current_result[$result->getFacet()->id()])) !== false) {
          unset($filters_current_result[$result->getFacet()->id()][$key]);
        }
        if ($result->getFacet()->getEnableParentWhenChildGetsDisabled() && $result->getFacet()->getUseHierarchy()) {
          // Enable parent id again if exists.
          $parent_ids = $result->getFacet()->getHierarchyInstance()->getParentIds($raw_value);
          if (isset($parent_ids[0]) && $parent_ids[0]) {
            $filters_current_result[$result->getFacet()->id()][] = $coder->encode($parent_ids[0]);
          }
        }
      }
      // If the value is not active, add the filter string.
      else {
        $filters_current_result[$result->getFacet()->id()][] = $raw_value;

        if ($result->getFacet()->getUseHierarchy()) {
          // If hierarchy is active, unset parent trail and every child when
          // building the enable-link to ensure those are not enabled anymore.
          $parent_ids = $result->getFacet()->getHierarchyInstance()->getParentIds($raw_value);
          $child_ids = $result->getFacet()->getHierarchyInstance()->getNestedChildIds($raw_value);
          $parents_and_child_ids = array_merge($parent_ids, $child_ids);
          foreach ($parents_and_child_ids as $id) {
            if (($key = array_search($id, $filters_current_result[$result->getFacet()->id()])) !== false) {
              unset($filters_current_result[$result->getFacet()->id()][$key]);
            }
          }
        }
        // Exclude currently active results from the filter params if we are in
        // the show_only_one_result mode.
        if ($result->getFacet()->getShowOnlyOneResult()) {
          foreach ($results as $result2) {
            if ($result2->isActive()) {
              if (($key = array_search($coder->encode($result2->getRawValue()), $filters_current_result[$facet->id()])) !== false) {
                unset($filters_current_result[$result->getFacet()->id()][$key]);
              }
            }
          }
        }
      }

      // Now we start transforming $filters_current_result array into a string
      // which we append later to the current path.
      $pretty_paths_string = "";
      foreach($filters_current_result as $facet_id => $active_values){
        foreach($active_values as $active_value){
          // Ensure we only load every facet and coder once.
          if(!isset($initialized_facets[$facet_id])){
            $facet = Facet::load($facet_id);
            $initialized_facets[$facet_id] = $facet;
            $coder_id = $facet->getThirdPartySetting('facets_pretty_paths', 'coder', 'default_coder');
            $coder = $coder_plugin_manager->createInstance($coder_id, ['facet' => $facet]);
            $initialized_coders[$facet_id] = $coder;
          }
          $encoded_value = $initialized_coders[$facet_id]->encode($active_value);
          $pretty_paths_string .= "/" . $initialized_facets[$facet_id]->getUrlAlias() . "/" . $encoded_value;
        }
      }

      $url = Url::fromUri('internal:' . $facet->getFacetSource()->getPath() . $pretty_paths_string);

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
   * Initializes the active filters from the url.
   *
   * Get all the filters that are active by checking the request url and store
   * them in activeFilters which is an array where key is the facet id and value
   * is an array of raw values.
   */
  protected function initializeActiveFilters($configuration) {

    $facet_source_id = $this->configuration['facet']->getFacetSourceId();

    // Do heavy lifting only once per facet source id.
    $mapping = &drupal_static('facets_pretty_paths_init',[]);
    if (!isset($mapping[$facet_source_id])) {
      $mapping[$facet_source_id] = [];
      $coder_plugin_manager = \Drupal::service('plugin.manager.facets_pretty_paths.coder');
      $initialized_coders = []; // Will hold all initialized coders.
      if ($filters = $this->routeMatch->getParameter('facets_query')) {
        $parts = explode('/', $filters);
        if(count($parts) % 2 !== 0){
          // Our key/value combination should always be even. If uneven, we just
          // assume that the first string is not part of the filters, and remove
          // it. This can occur when an url lives in the same path as our facet
          // source, e.g. /search/overview where /search is the facet source path.
          array_shift($parts);
        }
        foreach ($parts as $index => $part) {
          if ($index % 2 == 0) {
            $url_alias = $part;
          }
          else {
            $facet_id = $this->getFacetIdByUrlAlias($url_alias, $facet_source_id);
            if(!$facet_id){
              continue; // No valid facet url alias specified in url.
            }
            // Only initialize facet and their coder once per facet id.
            if(!isset($initialized_coders[$facet_id])){
              $facet = Facet::load($facet_id);
              $coder_id = $facet->getThirdPartySetting('facets_pretty_paths', 'coder', 'default_coder');
              $coder = $coder_plugin_manager->createInstance($coder_id, ['facet' => $facet]);
              $initialized_coders[$facet_id] = $coder;
            }
            if (!isset($mapping[$facet_source_id][$facet_id])) {
              $mapping[$facet_source_id][$facet_id] = [$initialized_coders[$facet_id]->decode($part)];
            }
            else {
              $mapping[$facet_source_id][$facet_id][] = $initialized_coders[$facet_id]->decode($part);
            }
          }
        }
      }
    }
    $this->activeFilters = $mapping[$facet_source_id];
  }

  /**
   * Gets the facet id from the url alias & facet source id.
   *
   * @param string $url_alias
   *   The url alias.
   * @param string $facet_source_id
   *   The facet source id.
   *
   * @return bool|string
   *   Either the facet id, or FALSE if that can't be loaded.
   */
  protected function getFacetIdByUrlAlias($url_alias, $facet_source_id) {
    $mapping = &drupal_static(__FUNCTION__);
    if (!isset($mapping[$facet_source_id][$url_alias])) {
      $storage = $this->entityTypeManager->getStorage('facets_facet');
      $facet = current($storage->loadByProperties(['url_alias' => $url_alias, 'facet_source_id' => $facet_source_id]));
      if (!$facet) {
        return NULL;
      }
      $mapping[$facet_source_id][$url_alias] = $facet->id();
    }
    return $mapping[$facet_source_id][$url_alias];
  }

}
