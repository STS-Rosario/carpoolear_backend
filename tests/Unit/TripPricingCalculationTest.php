<?php

namespace Tests\Unit;

use Mockery;
use Tests\TestCase;
use STS\Repository\TripRepository;
use STS\Services\GeoService;
use STS\Services\MercadoPagoService;
use STS\Services\MapboxDirectionsRouteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TripPricingCalculationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @dataProvider tripPricingProvider
     */
    public function testStoreTripInfoSuccessCalculatesExpectedTripPrices(
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
        $this->assertEquals($expected['recommended_trip_price_cents'], $response['data']['recommended_trip_price_cents']);
        $this->assertEquals($expected['maximum_trip_price_cents'], $response['data']['maximum_trip_price_cents']);
    }

    public function testCarpoolearConfigPreservesDecimalValuesFromEnv(): void
    {
        putenv('MODULE_MAX_PRICE_FUEL_PRICE=2378.5');
        putenv('MODULE_MAX_PRICE_PRICE_VARIANCE_TOLLS=10.5');
        putenv('MODULE_MAX_PRICE_PRICE_VARIANCE_MAX_EXTRA=15.75');
        putenv('MODULE_MAX_PRICE_KILOMETER_BY_LITER=12.5');

        $_ENV['MODULE_MAX_PRICE_FUEL_PRICE'] = '2378.5';
        $_ENV['MODULE_MAX_PRICE_PRICE_VARIANCE_TOLLS'] = '10.5';
        $_ENV['MODULE_MAX_PRICE_PRICE_VARIANCE_MAX_EXTRA'] = '15.75';
        $_ENV['MODULE_MAX_PRICE_KILOMETER_BY_LITER'] = '12.5';
        $_SERVER['MODULE_MAX_PRICE_FUEL_PRICE'] = '2378.5';
        $_SERVER['MODULE_MAX_PRICE_PRICE_VARIANCE_TOLLS'] = '10.5';
        $_SERVER['MODULE_MAX_PRICE_PRICE_VARIANCE_MAX_EXTRA'] = '15.75';
        $_SERVER['MODULE_MAX_PRICE_KILOMETER_BY_LITER'] = '12.5';

        $config = include base_path('config/carpoolear.php');

        $this->assertSame(2378.5, $config['module_max_price_fuel_price']);
        $this->assertSame(10.5, $config['module_max_price_price_variance_tolls']);
        $this->assertSame(15.75, $config['module_max_price_price_variance_max_extra']);
        $this->assertSame(12.5, $config['module_max_price_kilometer_by_liter']);
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
