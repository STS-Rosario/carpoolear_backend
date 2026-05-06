<?php

namespace Tests\Unit\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use ReflectionClass;
use STS\Console\Commands\BuildNodesSuburb;
use STS\Models\NodeGeo;
use Tests\TestCase;

class BuildNodesSuburbTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function commandWithoutConstructorWithClient(Client $client): BuildNodesSuburb
    {
        $reflection = new ReflectionClass(BuildNodesSuburb::class);
        /** @var BuildNodesSuburb $command */
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
                'address' => ['state' => 'Buenos Aires'],
            ])));

        $command = $this->commandWithoutConstructorWithClient($client);

        $this->assertSame('Buenos Aires', $command->geocodeState(-34.6, -58.4));
    }

    public function test_geocode_state_falls_back_to_county_when_state_absent(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->andReturn(new Response(200, [], json_encode([
                'address' => ['county' => 'Some County'],
            ])));

        $command = $this->commandWithoutConstructorWithClient($client);

        $this->assertSame('Some County', $command->geocodeState(-34.6, -58.4));
    }

    public function test_geocode_state_returns_zero_on_request_exception(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('get')
            ->once()
            ->andThrow(new \RuntimeException('network down'));

        $command = $this->commandWithoutConstructorWithClient($client);

        $this->assertSame(0, $command->geocodeState(-34.6, -58.4));
    }

    public function test_handle_uses_province_tag_without_geocode_lookup(): void
    {
        $tmpDir = sys_get_temp_dir().'/buildnodessuburb_'.uniqid();
        mkdir($tmpDir, 0777, true);

        file_put_contents($tmpDir.'/ARG.json', json_encode([
            'elements' => [[
                'tags' => [
                    'name' => 'Barrio Norte',
                    'place' => 'suburb',
                    'is_in:province' => 'Córdoba',
                ],
                'lat' => -31.42,
                'lon' => -64.18,
            ]],
        ]));

        $command = new class($tmpDir) extends BuildNodesSuburb
        {
            public function __construct(string $fixtureDir)
            {
                $this->dir = rtrim($fixtureDir, '/').'/';
                $this->files = ['ARG.json'];
                $this->client = new Client;
            }

            public function geocodeState($lat, $long)
            {
                throw new \RuntimeException('geocodeState should not be called');
            }

            public function info($string, $verbosity = null): void {}
        };

        $command->handle();

        $node = NodeGeo::query()->where('name', 'Barrio Norte')->first();
        $this->assertNotNull($node);
        $this->assertSame('Córdoba', $node->state);
        $this->assertSame('ARG', $node->country);
    }

    public function test_handle_geocodes_and_maps_brazil_state_short_code_when_missing_tags(): void
    {
        $tmpDir = sys_get_temp_dir().'/buildnodessuburb_'.uniqid();
        mkdir($tmpDir, 0777, true);

        file_put_contents($tmpDir.'/BRA.json', json_encode([
            'elements' => [[
                'tags' => [
                    'name' => 'Centro',
                    'place' => 'suburb',
                ],
                'lat' => -22.90,
                'lon' => -43.20,
            ]],
        ]));

        $command = new class($tmpDir) extends BuildNodesSuburb
        {
            public function __construct(string $fixtureDir)
            {
                $this->dir = rtrim($fixtureDir, '/').'/';
                $this->files = ['BRA.json'];
                $this->client = new Client;
            }

            public function geocodeState($lat, $long)
            {
                return 'RJ';
            }

            public function info($string, $verbosity = null): void {}
        };

        $command->handle();

        $node = NodeGeo::query()->where('name', 'Centro')->first();
        $this->assertNotNull($node);
        $this->assertSame('Río de Janeiro', $node->state);
        $this->assertSame('BRA', $node->country);
    }
}
