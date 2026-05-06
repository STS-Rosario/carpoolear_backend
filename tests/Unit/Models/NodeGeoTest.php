<?php

namespace Tests\Unit\Models;

use STS\Models\NodeGeo;
use Tests\TestCase;

class NodeGeoTest extends TestCase
{
    public function test_persists_core_attributes_via_force_fill_including_importance(): void
    {
        $token = uniqid('node_', true);
        $node = new NodeGeo;
        $node->forceFill([
            'name' => 'Test place '.$token,
            'lat' => -32.9465,
            'lng' => -60.6698,
            'type' => 'locality',
            'state' => 'Santa Fe',
            'country' => 'AR',
            'importance' => 5,
        ])->save();

        $node = $node->fresh();
        $this->assertSame('Test place '.$token, $node->name);
        $this->assertEqualsWithDelta(-32.9465, (float) $node->lat, 1e-6);
        $this->assertEqualsWithDelta(-60.6698, (float) $node->lng, 1e-6);
        $this->assertSame('locality', $node->type);
        $this->assertSame('Santa Fe', $node->state);
        $this->assertSame('AR', $node->country);
        $this->assertSame(5, (int) $node->importance);
    }

    public function test_fillable_mass_assignment_without_importance_then_set_importance(): void
    {
        $node = NodeGeo::query()->create([
            'name' => 'Fillable only',
            'lat' => -31.0,
            'lng' => -64.0,
            'type' => 'city',
            'state' => 'Córdoba',
            'country' => 'AR',
        ]);
        $node->forceFill(['importance' => 2])->save();

        $node = $node->fresh();
        $this->assertSame('Fillable only', $node->name);
        $this->assertSame(2, (int) $node->importance);
    }

    public function test_table_name_is_nodes_geo(): void
    {
        $this->assertSame('nodes_geo', (new NodeGeo)->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse((new NodeGeo)->timestamps);
    }
}
