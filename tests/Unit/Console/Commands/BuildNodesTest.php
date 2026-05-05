<?php

namespace Tests\Unit\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use ReflectionClass;
use STS\Console\Commands\BuildNodes;
use STS\Models\NodeGeo;
use Tests\TestCase;

class BuildNodesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function commandWithoutConstructorWithClient(Client $client): BuildNodes
    {
        $reflection = new ReflectionClass(BuildNodes::class);
        /** @var BuildNodes $command */
        $command = $reflection->newInstanceWithoutConstructor();
        $command->client = $client;

        return $command;
    }

    public function test_geocode_state_returns_state_when_present(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->andReturn(new Response(200, [], json_encode([
                'address' => ['state' => 'Cordoba'],
            ])));

        $command = $this->commandWithoutConstructorWithClient($client);

        $this->assertSame('Cordoba', $command->geocodeState(-31.4, -64.2));
    }

    public function test_geocode_state_returns_county_when_state_missing(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->andReturn(new Response(200, [], json_encode([
                'address' => ['county' => 'County Name'],
            ])));

        $command = $this->commandWithoutConstructorWithClient($client);

        $this->assertSame('County Name', $command->geocodeState(-31.4, -64.2));
    }

    public function test_geocode_state_returns_zero_when_request_fails(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        $command = $this->commandWithoutConstructorWithClient($client);

        $this->assertSame(0, $command->geocodeState(-31.4, -64.2));
    }

    public function test_handle_creates_node_and_maps_arg_state_short_code(): void
    {
        $tmpDir = sys_get_temp_dir().'/buildnodes_'.uniqid();
        mkdir($tmpDir, 0777, true);

        file_put_contents($tmpDir.'/ARG.json', json_encode([
            'features' => [[
                'properties' => [
                    'name' => 'Cordoba Capital',
                    'place' => 'city',
                ],
                'geometry' => [
                    'coordinates' => [-64.1888, -31.4201],
                ],
            ]],
        ]));

        $command = new class($tmpDir) extends BuildNodes
        {
            private string $fixtureDir;

            public function __construct(string $fixtureDir)
            {
                $this->fixtureDir = $fixtureDir;
                parent::__construct();
                $this->dir = rtrim($fixtureDir, '/').'/';
                $this->files = ['ARG.json'];
            }

            public function geocodeState($lat, $long)
            {
                return 'PBA';
            }

            public function info($string, $verbosity = null): void {}
        };

        $command->handle();

        $node = NodeGeo::query()->where('name', 'Cordoba Capital')->first();
        $this->assertNotNull($node);
        $this->assertSame('Buenos Aires', $node->state);
        $this->assertSame('ARG', $node->country);
    }
}
