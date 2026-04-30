<?php

namespace Tests\Unit;

use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use STS\Repository\TripRepository;
use STS\Services\GeoService;
use STS\Services\MapboxDirectionsRouteService;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class TripPricingCalculationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[DataProvider('tripPricingProvider')]
    public function test_store_trip_info_success_calculates_expected_trip_prices(
        float $distanceMeters,
        float $fuelPrice,
        float $kilometersPerLiter,
        float $tollsVariancePercent,
        float $maxPriceVariancePercent,
        bool $selladoEnabled,
        int $selladoAmountCents,
        bool $routeNeedsPayment
    ): void {
        config()->set('carpoolear.module_max_price_fuel_price', $fuelPrice);
        config()->set('carpoolear.module_max_price_kilometer_by_liter', $kilometersPerLiter);
        config()->set('carpoolear.module_max_price_price_variance_tolls', $tollsVariancePercent);
        config()->set('carpoolear.module_max_price_price_variance_max_extra', $maxPriceVariancePercent);
        config()->set('carpoolear.module_trip_creation_payment_enabled', $selladoEnabled);
        config()->set('carpoolear.module_trip_creation_payment_amount_cents', $selladoAmountCents);

        $geoService = Mockery::mock(GeoService::class);
        $geoService->shouldReceive('getPaidRegions')->andReturn([]);
        $geoService->shouldReceive('doStopsRequireSellado')->once()->andReturn($routeNeedsPayment);

        $mercadoPagoService = Mockery::mock(MercadoPagoService::class);
        $mapboxDirectionsRouteService = Mockery::mock(MapboxDirectionsRouteService::class);
        $repository = new TripRepository($geoService, $mercadoPagoService, $mapboxDirectionsRouteService);

        $response = $this->invokeStoreTripInfoSuccess($repository, $distanceMeters);
        $expected = $this->calculateExpectedPricing(
            $distanceMeters,
            $fuelPrice,
            $kilometersPerLiter,
            $tollsVariancePercent,
            $maxPriceVariancePercent,
            $selladoEnabled ? $selladoAmountCents : 0
        );

        $this->assertTrue($response['status']);
        $this->assertSame('Route found', $response['message']);
        $this->assertSame($distanceMeters, (float) $response['data']['distance']);
        $this->assertSame(11805.6, (float) $response['data']['duration']);
        $this->assertSame($routeNeedsPayment, (bool) $response['data']['route_needs_payment']);
        $this->assertEqualsWithDelta($distanceMeters * 0.15, (float) $response['data']['co2'], 0.0001);
        $this->assertEquals($expected['recommended_trip_price_cents'], $response['data']['recommended_trip_price_cents']);
        $this->assertEquals($expected['maximum_trip_price_cents'], $response['data']['maximum_trip_price_cents']);
    }

    public function test_carpoolear_config_preserves_decimal_values_from_env(): void
    {
        $keys = [
            'MODULE_MAX_PRICE_FUEL_PRICE' => '2378.5',
            'MODULE_MAX_PRICE_PRICE_VARIANCE_TOLLS' => '10.5',
            'MODULE_MAX_PRICE_PRICE_VARIANCE_MAX_EXTRA' => '15.75',
            'MODULE_MAX_PRICE_KILOMETER_BY_LITER' => '12.5',
        ];
        $previous = [];

        foreach ($keys as $key => $value) {
            $previous[$key] = getenv($key);
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            $config = include base_path('config/carpoolear.php');

            $this->assertSame(2378.5, $config['module_max_price_fuel_price']);
            $this->assertSame(10.5, $config['module_max_price_price_variance_tolls']);
            $this->assertSame(15.75, $config['module_max_price_price_variance_max_extra']);
            $this->assertSame(12.5, $config['module_max_price_kilometer_by_liter']);
        } finally {
            foreach (array_keys($keys) as $key) {
                $old = $previous[$key];
                if ($old === false || $old === null) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                } else {
                    putenv($key.'='.$old);
                    $_ENV[$key] = $old;
                    $_SERVER[$key] = $old;
                }
            }
        }
    }

    public static function tripPricingProvider(): array
    {
        return [
            'rosario-bsas-no-sellado-km10-fuel1000' => [
                291088.8,
                1000.0,
                10.0,
                10.0,
                15.0,
                false,
                0,
                false,
            ],
            'rosario-bsas-with-sellado-km12.5-fuel2178' => [
                291088.8,
                2178.0,
                12.5,
                10.0,
                15.0,
                true,
                12000,
                true,
            ],
            'short-route-with-sellado-km12.5-fuel2378.5' => [
                12650.4,
                2378.5,
                12.5,
                10.0,
                15.0,
                true,
                9500,
                true,
            ],
        ];
    }

    private function calculateExpectedPricing(
        float $distanceMeters,
        float $fuelPrice,
        float $kilometersPerLiter,
        float $tollsVariancePercent,
        float $maxPriceVariancePercent,
        int $selladoCents
    ): array {
        $pricePerKilometer = $fuelPrice / $kilometersPerLiter;
        $basePriceCents = (int) round(($distanceMeters / 1000) * $pricePerKilometer * 100);
        $tollsVarianceCents = (int) round($basePriceCents * ($tollsVariancePercent / 100));

        return [
            'recommended_trip_price_cents' => $basePriceCents + $tollsVarianceCents + $selladoCents,
            'maximum_trip_price_cents' => (int) round(($basePriceCents + $tollsVarianceCents) * (1 + $maxPriceVariancePercent / 100)) + $selladoCents,
        ];
    }

    private function invokeStoreTripInfoSuccess(TripRepository $repository, float $distanceMeters): array
    {
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('storeTripInfoSuccess');
        $method->setAccessible(true);

        return $method->invoke(
            $repository,
            [
                ['lat' => -34.6075682, 'lng' => -58.4370894],
                ['lat' => -32.9595004, 'lng' => -60.6615415],
            ],
            hash('sha256', 'rosario-buenos-aires'),
            $distanceMeters,
            11805.6,
            'test'
        );
    }
}
