<?php

namespace Tests\Unit\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use ReflectionClass;
use STS\Console\Commands\BuildNodes;
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
}
