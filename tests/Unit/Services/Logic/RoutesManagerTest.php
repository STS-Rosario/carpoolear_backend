<?php

namespace Tests\Unit\Services\Logic;

use STS\Models\NodeGeo;
use STS\Models\Route;
use STS\Repository\RoutesRepository as RoutesRep;
use STS\Services\Logic\RoutesManager;
use Tests\TestCase;

final class RoutesManagerWithFixture extends RoutesManager
{
    public function __construct(RoutesRep $routesRepo, private array $osrmFixture)
    {
        parent::__construct($routesRepo);
    }

    protected function fetchOsrmRouteJson(string $url)
    {
        return $this->osrmFixture;
    }
}

class RoutesManagerTest extends TestCase
{
    private function manager(): RoutesManager
    {
        return new RoutesManager(new RoutesRep);
    }

    private function makeNode(array $overrides = []): NodeGeo
    {
        $defaults = [
            'name' => 'Place '.substr(uniqid('', true), 0, 8),
            'lat' => -34.6,
            'lng' => -58.4,
            'type' => 'city',
            'state' => 'BA',
            'country' => 'AR',
            'importance' => 1,
        ];
        $node = new NodeGeo;
        $node->forceFill(array_merge($defaults, $overrides));
        $node->save();

        return $node->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function osrmFixtureAlongCorridor(float $lng1, float $lat1, float $lng2, float $lat2, int $segments = 8): array
    {
        $steps = [];
        for ($k = 0; $k < $segments; $k++) {
            $t0 = $k / $segments;
            $t1 = ($k + 1) / $segments;
            $lngA = $lng1 + ($lng2 - $lng1) * $t0;
            $latA = $lat1 + ($lat2 - $lat1) * $t0;
            $lngB = $lng1 + ($lng2 - $lng1) * $t1;
            $latB = $lat1 + ($lat2 - $lat1) * $t1;
            $steps[] = [
                'intersections' => [
                    ['location' => [$lngA, $latA]],
                    ['location' => [$lngB, $latB]],
                ],
            ];
        }

        return [
            'routes' => [
                [
                    'legs' => [
                        [
                            'steps' => $steps,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $locations
     * @return array<string, mixed>
     */
    private function osrmFixtureWithLocations(array $locations): array
    {
        $steps = [];
        for ($i = 1; $i < count($locations); $i++) {
            $steps[] = [
                'intersections' => [
                    ['location' => $locations[$i - 1]],
                    ['location' => $locations[$i]],
                ],
            ];
        }

        return [
            'routes' => [
                [
                    'legs' => [
                        [
                            'steps' => $steps,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_autocomplete_delegates_to_repository(): void
    {
        $suffix = substr(uniqid('', true), 0, 8);
        $this->makeNode([
            'name' => 'MgrCity '.$suffix,
            'state' => 'X',
            'country' => 'AR',
            'importance' => 5,
        ]);
        $this->makeNode([
            'name' => 'MgrCity '.$suffix,
            'state' => 'Y',
            'country' => 'UY',
            'importance' => 10,
        ]);

        $manager = $this->manager();

        $arOnly = $manager->autocomplete('MgrCity '.$suffix, 'AR', false);
        $this->assertCount(1, $arOnly);
        $this->assertSame('AR', $arOnly->first()->country);

        $multi = $manager->autocomplete('MgrCity '.$suffix, 'AR', true);
        $this->assertGreaterThanOrEqual(2, $multi->count());
    }

    public function test_autocomplete_orders_by_importance_and_limits_five(): void
    {
        $needle = 'MgrRank'.substr(uniqid('', true), 0, 6);
        for ($i = 1; $i <= 6; $i++) {
            $this->makeNode([
                'name' => $needle.' '.$i,
                'country' => 'AR',
                'state' => 'BA',
                'importance' => $i,
            ]);
        }

        $rows = $this->manager()->autocomplete($needle, 'AR', false);
        $this->assertCount(5, $rows);
        $this->assertSame(6, (int) $rows->first()->importance);
    }

    public function test_autocomplete_returns_empty_collection_when_no_match(): void
    {
        $rows = $this->manager()->autocomplete('NoSuchCity-'.uniqid('', true), 'AR', false);

        $this->assertCount(0, $rows);
    }

    public function test_create_route_with_osrm_fixture_syncs_near_nodes_and_marks_processed(): void
    {
        $n1 = $this->makeNode(['lat' => -34.0, 'lng' => -58.0, 'name' => 'RFrom']);
        $n2 = $this->makeNode(['lat' => -35.0, 'lng' => -60.0, 'name' => 'RTo']);
        $mid = $this->makeNode(['lat' => -34.5, 'lng' => -59.0, 'name' => 'RMid']);

        $route = new Route;
        $route->forceFill([
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'processed' => false,
        ])->save();

        $fixture = $this->osrmFixtureAlongCorridor(-58.0, -34.0, -60.0, -35.0);
        $manager = new RoutesManagerWithFixture(new RoutesRep, $fixture);
        $route->load(['origin', 'destiny']);

        $manager->createRoute($route);

        $route->refresh();
        $this->assertTrue((bool) $route->processed);
        $attached = $route->nodes()->pluck('nodes_geo.id')->all();
        $this->assertNotEmpty($attached);
        $this->assertContains($mid->id, $attached);
    }

    public function test_create_route_handles_zero_lat_delta_segments(): void
    {
        $n1 = $this->makeNode(['lat' => -34.0, 'lng' => -58.0, 'name' => 'ZFrom']);
        $n2 = $this->makeNode(['lat' => -34.0, 'lng' => -60.0, 'name' => 'ZTo']);
        $near = $this->makeNode(['lat' => -34.0, 'lng' => -59.0, 'name' => 'ZNear']);

        $route = new Route;
        $route->forceFill([
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'processed' => false,
        ])->save();

        $fixture = $this->osrmFixtureWithLocations([
            [-58.0, -34.0],
            [-59.0, -34.0],
            [-60.0, -34.0],
        ]);
        $manager = new RoutesManagerWithFixture(new RoutesRep, $fixture);
        $route->load(['origin', 'destiny']);

        $manager->createRoute($route);

        $route->refresh();
        $this->assertTrue((bool) $route->processed);
        $attached = $route->nodes()->pluck('nodes_geo.id')->all();
        $this->assertContains($near->id, $attached);
    }

    public function test_create_route_deduplicates_repeated_near_node_candidates(): void
    {
        $n1 = $this->makeNode(['lat' => -34.0, 'lng' => -58.0, 'name' => 'DFrom']);
        $n2 = $this->makeNode(['lat' => -35.0, 'lng' => -60.0, 'name' => 'DTo']);
        $near = $this->makeNode(['lat' => -34.5, 'lng' => -59.0, 'name' => 'DNear']);

        $route = new Route;
        $route->forceFill([
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'processed' => false,
        ])->save();

        $fixture = $this->osrmFixtureWithLocations([
            [-58.0, -34.0],
            [-58.5, -34.25],
            [-59.0, -34.5],
            [-59.5, -34.75],
            [-60.0, -35.0],
        ]);
        $manager = new RoutesManagerWithFixture(new RoutesRep, $fixture);
        $route->load(['origin', 'destiny']);

        $manager->createRoute($route);

        $route->refresh();
        $this->assertTrue((bool) $route->processed);
        $attached = $route->nodes()->pluck('nodes_geo.id')->all();
        $this->assertCount(count(array_unique($attached)), $attached);
        $this->assertContains($near->id, $attached);
    }

    public function test_create_route_marks_processed_even_when_no_near_nodes_are_found(): void
    {
        $n1 = $this->makeNode(['lat' => -10.0, 'lng' => -10.0, 'name' => 'NFrom']);
        $n2 = $this->makeNode(['lat' => -11.0, 'lng' => -11.0, 'name' => 'NTo']);

        $route = new Route;
        $route->forceFill([
            'from_id' => $n1->id,
            'to_id' => $n2->id,
            'processed' => false,
        ])->save();

        $fixture = $this->osrmFixtureWithLocations([
            [120.0, 40.0],
            [121.0, 41.0],
            [122.0, 42.0],
        ]);
        $manager = new RoutesManagerWithFixture(new RoutesRep, $fixture);
        $route->load(['origin', 'destiny']);

        $manager->createRoute($route);

        $route->refresh();
        $this->assertTrue((bool) $route->processed);
        $this->assertCount(0, $route->nodes()->pluck('nodes_geo.id')->all());
    }
}
