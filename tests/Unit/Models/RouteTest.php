<?php

namespace Tests\Unit\Models;

use STS\Models\NodeGeo;
use STS\Models\Route;
use Tests\TestCase;

class RouteTest extends TestCase
{
    private function makeNodeGeo(string $nameSuffix): NodeGeo
    {
        $node = new NodeGeo;
        $node->forceFill([
            'name' => 'Route node '.$nameSuffix,
            'lat' => -30.0,
            'lng' => -59.0,
            'type' => 'city',
            'state' => 'SF',
            'country' => 'AR',
            'importance' => 1,
        ])->save();

        return $node->fresh();
    }

    public function test_origin_and_destiny_belong_to_node_geo(): void
    {
        $from = $this->makeNodeGeo('from');
        $to = $this->makeNodeGeo('to');

        $route = new Route;
        $route->forceFill([
            'from_id' => $from->id,
            'to_id' => $to->id,
            'processed' => false,
        ])->save();

        $route = $route->fresh();
        $this->assertTrue($route->origin()->first()->is($from));
        $this->assertTrue($route->destiny()->first()->is($to));
    }

    public function test_nodes_many_to_many_attaches_node_geo_ids(): void
    {
        $from = $this->makeNodeGeo('a');
        $to = $this->makeNodeGeo('b');
        $via = $this->makeNodeGeo('via');

        $route = new Route;
        $route->forceFill([
            'from_id' => $from->id,
            'to_id' => $to->id,
            'processed' => false,
        ])->save();

        $route->nodes()->sync([$from->id, $via->id, $to->id]);

        $route = $route->fresh();
        $this->assertSame(3, $route->nodes()->count());
        $this->assertEqualsCanonicalizing(
            [$from->id, $to->id, $via->id],
            $route->nodes->pluck('id')->all()
        );
    }

    public function test_table_name_is_routes(): void
    {
        $this->assertSame('routes', (new Route)->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse((new Route)->timestamps);
    }
}
