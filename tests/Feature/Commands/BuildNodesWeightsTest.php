<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use STS\Models\NodeGeo;
use STS\Models\Route;
use Tests\TestCase;

class BuildNodesWeightsTest extends TestCase
{
    protected function createNode(string $name, string $type): NodeGeo
    {
        return NodeGeo::create([
            'name' => $name,
            'lat' => -32.9,
            'lng' => -60.6,
            'type' => $type,
            'importance' => 0,
        ]);
    }

    protected function createRoute(int $fromId, int $toId): Route
    {
        return Route::create([
            'from_id' => $fromId,
            'to_id' => $toId,
        ]);
    }

    public function test_city_node_gets_base_importance1000()
    {
        $city = $this->createNode('Rosario', 'city');

        $this->artisan('node:buildweights')->assertSuccessful();

        $city->refresh();
        $this->assertEquals(1000, $city->importance);
    }

    public function test_town_node_gets_base_importance500()
    {
        $town = $this->createNode('Funes', 'town');

        $this->artisan('node:buildweights')->assertSuccessful();

        $town->refresh();
        $this->assertEquals(500, $town->importance);
    }

    public function test_village_node_gets_base_importance200()
    {
        $village = $this->createNode('Ibarlucea', 'village');

        $this->artisan('node:buildweights')->assertSuccessful();

        $village->refresh();
        $this->assertEquals(200, $village->importance);
    }

    public function test_route_origin_gets1000_per_route()
    {
        $origin = $this->createNode('Origin', 'hamlet');
        $dest = $this->createNode('Dest', 'hamlet');

        $this->createRoute($origin->id, $dest->id);
        $this->createRoute($origin->id, $dest->id);

        $this->artisan('node:buildweights')->assertSuccessful();

        $origin->refresh();
        // 2 routes * 1000 = 2000
        $this->assertEquals(2000, $origin->importance);
    }

    public function test_route_destination_gets1000_per_route()
    {
        $origin = $this->createNode('Origin', 'hamlet');
        $dest = $this->createNode('Dest', 'hamlet');

        $this->createRoute($origin->id, $dest->id);
        $this->createRoute($origin->id, $dest->id);
        $this->createRoute($origin->id, $dest->id);

        $this->artisan('node:buildweights')->assertSuccessful();

        $dest->refresh();
        // 3 routes * 1000 = 3000
        $this->assertEquals(3000, $dest->importance);
    }

    public function test_intermediate_route_node_gets10_per_occurrence()
    {
        $origin = $this->createNode('Origin', 'hamlet');
        $dest = $this->createNode('Dest', 'hamlet');
        $waypoint = $this->createNode('Waypoint', 'hamlet');

        $routeA = $this->createRoute($origin->id, $dest->id);
        $routeB = $this->createRoute($origin->id, $dest->id);

        // Waypoint appears in both routes
        DB::table('route_nodes')->insert([
            ['route_id' => $routeA->id, 'node_id' => $waypoint->id],
            ['route_id' => $routeB->id, 'node_id' => $waypoint->id],
        ]);

        $this->artisan('node:buildweights')->assertSuccessful();

        $waypoint->refresh();
        // 2 occurrences * 10 = 20
        $this->assertEquals(20, $waypoint->importance);
    }

    public function test_combines_all_importance_sources()
    {
        // A city that is also a route origin and destination
        $city = $this->createNode('Rosario', 'city');
        $other = $this->createNode('Santa Fe', 'city');

        // City is origin of 2 routes and destination of 3
        // (distinct counts to avoid UNION deduplication — the SQL uses UNION
        // not UNION ALL, so identical (id, importance) pairs get merged)
        $route1 = $this->createRoute($city->id, $other->id);
        $route2 = $this->createRoute($city->id, $other->id);
        $route3 = $this->createRoute($other->id, $city->id);
        $route4 = $this->createRoute($other->id, $city->id);
        $route5 = $this->createRoute($other->id, $city->id);

        // City also appears as intermediate waypoint once
        DB::table('route_nodes')->insert([
            'route_id' => $route3->id,
            'node_id' => $city->id,
        ]);

        $this->artisan('node:buildweights')->assertSuccessful();

        $city->refresh();
        // city base: 1000 + origin(2*1000) + dest(3*1000) + waypoint(1*10) = 6010
        $this->assertEquals(6010, $city->importance);
    }

    public function test_node_with_no_factors_keeps_zero_importance()
    {
        // A node type that isn't city/town/village and has no routes
        $node = $this->createNode('Unknown', 'hamlet');

        $this->artisan('node:buildweights')->assertSuccessful();

        $node->refresh();
        $this->assertEquals(0, $node->importance);
    }
}
