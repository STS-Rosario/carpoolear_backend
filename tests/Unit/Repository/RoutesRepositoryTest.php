<?php

namespace Tests\Unit\Repository;

use STS\Models\NodeGeo;
use STS\Models\Route;
use STS\Repository\RoutesRepository;
use Tests\TestCase;

class RoutesRepositoryTest extends TestCase
{
    private function repo(): RoutesRepository
    {
        return new RoutesRepository;
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

    public function test_get_potentials_nodes_returns_nodes_inside_expanded_bounding_box(): void
    {
        $n1 = $this->makeNode(['lat' => -34.0, 'lng' => -58.0]);
        $n2 = $this->makeNode(['lat' => -35.0, 'lng' => -60.0]);
        $inside = $this->makeNode(['lat' => -34.5, 'lng' => -59.0, 'name' => 'InsideBox']);
        $this->makeNode(['lat' => 10.0, 'lng' => 10.0, 'name' => 'FarAway']);

        $rows = $this->repo()->getPotentialsNodes($n1, $n2);

        $ids = $rows->pluck('id');
        $this->assertTrue($ids->contains($inside->id));
        $this->assertTrue($ids->contains($n1->id));
        $this->assertTrue($ids->contains($n2->id));
    }

    public function test_autocomplete_filters_by_country_when_not_multicountry(): void
    {
        $suffix = substr(uniqid('', true), 0, 8);
        $this->makeNode([
            'name' => 'UniqueCity '.$suffix,
            'state' => 'X',
            'country' => 'AR',
            'importance' => 5,
        ]);
        $this->makeNode([
            'name' => 'UniqueCity '.$suffix,
            'state' => 'Y',
            'country' => 'UY',
            'importance' => 10,
        ]);

        $arOnly = $this->repo()->autocomplete('UniqueCity '.$suffix, 'AR', false);
        $this->assertCount(1, $arOnly);
        $this->assertSame('AR', $arOnly->first()->country);

        $multi = $this->repo()->autocomplete('UniqueCity '.$suffix, 'AR', true);
        $this->assertGreaterThanOrEqual(2, $multi->count());
    }

    public function test_autocomplete_orders_by_importance_desc_and_limits_five(): void
    {
        $needle = 'StackRank'.substr(uniqid('', true), 0, 6);
        for ($i = 1; $i <= 6; $i++) {
            $this->makeNode([
                'name' => $needle.' '.$i,
                'country' => 'AR',
                'state' => 'BA',
                'importance' => $i,
            ]);
        }

        $rows = $this->repo()->autocomplete($needle, 'AR', false);
        $this->assertCount(5, $rows);
        $this->assertSame(6, (int) $rows->first()->importance);
    }

    public function test_save_route_syncs_nodes_and_marks_processed(): void
    {
        $a = $this->makeNode(['name' => 'NodeA']);
        $b = $this->makeNode(['name' => 'NodeB']);
        $c = $this->makeNode(['name' => 'NodeC']);
        $route = new Route([
            'from_id' => $a->id,
            'to_id' => $c->id,
            'processed' => false,
        ]);
        $route->save();

        $this->repo()->saveRoute($route, [$a, $b, $c]);

        $route->refresh();
        $this->assertTrue((bool) $route->processed);
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id],
            $route->nodes()->pluck('nodes_geo.id')->all()
        );
    }
}
