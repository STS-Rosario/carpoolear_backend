<?php

namespace Tests\Unit\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use ReflectionClass;
use STS\Console\Commands\BuildNodesSuburb;
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
}
