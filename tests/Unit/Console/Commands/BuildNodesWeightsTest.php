<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\DB;
use Mockery;
use STS\Console\Commands\BuildNodesWeights;
use STS\Services\Logic\RoutesManager;
use Tests\TestCase;

class BuildNodesWeightsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_recomputes_importance_from_type_routes_and_route_nodes(): void
    {
        DB::table('nodes_geo')->insert([
            ['id' => 1001, 'name' => 'City Node', 'lat' => -34.60, 'lng' => -58.40, 'type' => 'city', 'importance' => 0],
            ['id' => 1002, 'name' => 'Town Node', 'lat' => -34.61, 'lng' => -58.41, 'type' => 'town', 'importance' => 0],
            ['id' => 1003, 'name' => 'Village Node', 'lat' => -34.62, 'lng' => -58.42, 'type' => 'village', 'importance' => 0],
        ]);

        DB::table('routes')->insert([
            ['id' => 9001, 'from_id' => 1001, 'to_id' => 1002, 'processed' => false],
        ]);
        DB::table('route_nodes')->insert([
            ['route_id' => 9001, 'node_id' => 1001],
            ['route_id' => 9001, 'node_id' => 1002],
        ]);

        $command = new BuildNodesWeights(Mockery::mock(RoutesManager::class));
        $command->handle();

        $this->assertSame(1010, (int) DB::table('nodes_geo')->where('id', 1001)->value('importance'));
        $this->assertSame(1510, (int) DB::table('nodes_geo')->where('id', 1002)->value('importance'));
        $this->assertSame(200, (int) DB::table('nodes_geo')->where('id', 1003)->value('importance'));
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new BuildNodesWeights(Mockery::mock(RoutesManager::class));

        $this->assertSame('node:buildweights', $command->getName());
        $this->assertStringContainsString('Calculate the weights for nodes', $command->getDescription());
    }
}
