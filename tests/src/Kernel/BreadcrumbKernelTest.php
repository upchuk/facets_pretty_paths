<?php

namespace Drupal\Tests\facets_pretty_paths\Kernel;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

class BreadcrumbKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'facets',
    'facets_pretty_paths',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['system']);
  }

  /**
   * Tests the Pretty Paths breadcrumb builder.
   */
  public function testBreadcrumb() {
    // Initialise the current request and route match.
    $stack = $this->container->get('request_stack');

    // We need to use a dummy, albeit existing route (in this case system.admin)
    // so that Url objects can be built in the breadcrumb builder. Otherwise
    // it will not find the missing routes.
    $route = new Route('admin/{facets_query}/{f0}/{f1}/{f2}/{f3}/{f4}/{f5}/{f6}/{f7}/{f8}/{f9}/{f10}/{f11}/{f12}/{f13}/{f14}/{f15}/{f16}/{f17}/{f18}/{f19}/{f20}/{f21}/{f22}/{f23}/{f24}/{f25}/{f26}/{f27}/{f28}/{f29}/{f30}/{f31}/{f32}/{f33}/{f34}/{f35}/{f36}/{f37}/{f38}');
    $route->setDefault('_title', 'My search title');
    $route->setRequirement('facets_query', '.*');
    $route->setDefault('facets_query', '');

    $request = Request::create('facets-search/content_type/page');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'system.admin');
    $request->attributes->set('facets_query', 'content_type/page');

    $stack->push($request);

    $current_route_match = $this->container->get('current_route_match');

    /** @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumb_builder */
    $breadcrumb_builder = $this->container->get('facets_pretty_paths.breadcrumb');
    $this->assertTrue($breadcrumb_builder->applies($current_route_match));
    $breadcrumb = $breadcrumb_builder->build($current_route_match);
    $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
    $this->assertCount(2, $breadcrumb->getLinks());

    $links = $breadcrumb->getLinks();
    $this->assertEquals('Home', $links[0]->getText()->render());
    $this->assertEquals('<front>', $links[0]->getUrl()->getRouteName());
    $this->assertEquals('My search title', $links[1]->getText()->render());
    $this->assertEquals('system.admin', $links[1]->getUrl()->getRouteName());
  }

}