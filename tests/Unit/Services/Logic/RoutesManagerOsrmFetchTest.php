<?php

namespace Tests\Unit\Services\Logic;

use Mockery;
use STS\Repository\RoutesRepository as RoutesRep;
use STS\Services\Logic\RoutesManager;
use Tests\TestCase;

/**
 * Exposes {@see RoutesManager::fetchOsrmRouteJson} for deterministic curl/JSON coverage.
 */
final class RoutesManagerFetchProxy extends RoutesManager
{
    /**
     * @return array<string, mixed>|null
     */
    public function exposeFetchOsrmRouteJson(string $url): ?array
    {
        return $this->fetchOsrmRouteJson($url);
    }
}

class RoutesManagerOsrmFetchTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fetch_osrm_route_json_decodes_local_json_via_curl(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'osrm');
        $this->assertNotFalse($tmp);

        $payload = [
            'routes' => [
                [
                    'legs' => [
                        [
                            'steps' => [
                                ['intersections' => [['location' => [1.0, 2.0]]]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($tmp, json_encode($payload, JSON_THROW_ON_ERROR));
        $url = 'file://'.str_replace('\\', '/', $tmp);

        $repo = Mockery::mock(RoutesRep::class);
        $mgr = new RoutesManagerFetchProxy($repo);

        try {
            $decoded = $mgr->exposeFetchOsrmRouteJson($url);
        } finally {
            @unlink($tmp);
        }

        $this->assertIsArray($decoded);
        $this->assertEqualsWithDelta(2.0, (float) $decoded['routes'][0]['legs'][0]['steps'][0]['intersections'][0]['location'][1], 0.0001);
    }
}
