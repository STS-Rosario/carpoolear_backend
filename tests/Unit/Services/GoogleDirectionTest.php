<?php

namespace Tests\Unit\Services;

use Mockery;
use STS\Models\TripPoint;
use STS\Services\GoogleDirection;
use Tests\TestCase;

final class GoogleDirectionHarness extends GoogleDirection
{
    /** @var list<array<string, mixed>|null> */
    private array $responseQueue = [];

    /** @var list<string> */
    public array $fetchedUrls = [];

    /**
     * @param  list<array<string, mixed>|null>  $queue
     */
    public function setResponseQueue(array $queue): void
    {
        $this->responseQueue = $queue;
    }

    protected function fetchGeocodeJson(string $url): ?array
    {
        $this->fetchedUrls[] = $url;

        return array_shift($this->responseQueue);
    }
}

class GoogleDirectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_download_geocodes_from_and_to_towns_and_saves_two_points(): void
    {
        $fixture = $this->minimalGeocodeFixture(-34.6, -58.4);

        $svc = new GoogleDirectionHarness;
        $svc->setResponseQueue([$fixture, $fixture]);

        $relation = Mockery::mock();
        $relation->shouldReceive('save')
            ->twice()
            ->with(Mockery::type(TripPoint::class));

        $trip = Mockery::mock(\stdClass::class);
        $trip->from_town = 'Rosario, AR';
        $trip->to_town = 'Córdoba, AR';
        $trip->shouldReceive('points')->twice()->andReturn($relation);

        $svc->download($trip);

        $this->assertCount(2, $svc->fetchedUrls);
        $this->assertStringContainsString(urlencode('Rosario, AR'), $svc->fetchedUrls[0]);
        $this->assertStringContainsString(urlencode('Córdoba, AR'), $svc->fetchedUrls[1]);
    }

    public function test_donwload_point_saves_trip_point_with_address_components_when_status_ok(): void
    {
        $fixture = $this->minimalGeocodeFixture(-31.4, -64.2);

        $svc = new GoogleDirectionHarness;
        $svc->setResponseQueue([$fixture]);

        $relation = Mockery::mock();
        $relation->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (TripPoint $point): bool {
                return $point->address === 'One Street'
                    && (float) $point->lat === -31.4
                    && (float) $point->lng === -64.2
                    && ($point->json_address['pais'] ?? null) === 'Argentina'
                    && ($point->json_address['provincia'] ?? null) === 'Córdoba'
                    && ($point->json_address['ciudad'] ?? null) === 'Capital'
                    && ($point->json_address['calle'] ?? null) === 'Av Test'
                    && ($point->json_address['numero'] ?? null) === '99';
            }));

        $trip = Mockery::mock(\stdClass::class);
        $trip->shouldReceive('points')->once()->andReturn($relation);

        $svc->donwloadPoint($trip, 'One Street');

        $this->assertCount(1, $svc->fetchedUrls);
        $this->assertStringContainsString(urlencode('One Street'), $svc->fetchedUrls[0]);
    }

    public function test_donwload_point_skips_save_when_geocode_status_not_ok(): void
    {
        $svc = new GoogleDirectionHarness;
        $svc->setResponseQueue([['status' => 'ZERO_RESULTS', 'results' => []]]);

        $trip = new \stdClass;

        $svc->donwloadPoint($trip, 'Unknown Place');

        $this->assertCount(1, $svc->fetchedUrls);
        $this->assertStringContainsString(urlencode('Unknown Place'), $svc->fetchedUrls[0]);
    }

    public function test_donwload_point_skips_save_when_fetch_returns_null(): void
    {
        $svc = new GoogleDirectionHarness;
        $svc->setResponseQueue([null]);

        $trip = new \stdClass;

        $svc->donwloadPoint($trip, 'Any');

        $this->assertCount(1, $svc->fetchedUrls);
        $this->assertStringContainsString(urlencode('Any'), $svc->fetchedUrls[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalGeocodeFixture(float $lat, float $lng): array
    {
        return [
            'status' => 'OK',
            'results' => [[
                'geometry' => [
                    'location' => ['lat' => $lat, 'lng' => $lng],
                ],
                'address_components' => [
                    ['long_name' => 'Argentina', 'types' => ['country', 'political']],
                    ['long_name' => 'Córdoba', 'types' => ['administrative_area_level_1', 'political']],
                    ['long_name' => 'Capital', 'types' => ['locality', 'political']],
                    ['long_name' => 'Av Test', 'types' => ['route']],
                    ['long_name' => '99', 'types' => ['street_number']],
                ],
            ]],
        ];
    }
}
