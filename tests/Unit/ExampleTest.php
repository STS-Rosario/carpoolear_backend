<?php

namespace Tests\Unit;

use STS\Repository\TripRepository;
use STS\Services\Logic\TripsManager;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_application_container_resolves_core_trip_services(): void
    {
        $repo = $this->app->make(TripRepository::class);
        $manager = $this->app->make(TripsManager::class);

        $this->assertInstanceOf(TripRepository::class, $repo);
        $this->assertInstanceOf(TripsManager::class, $manager);
    }

    public function test_config_exposes_expected_pricing_keys(): void
    {
        $this->assertNotNull(config('carpoolear.module_max_price_fuel_price'));
        $this->assertNotNull(config('carpoolear.module_max_price_kilometer_by_liter'));
        $this->assertNotNull(config('carpoolear.module_max_price_price_variance_tolls'));
        $this->assertNotNull(config('carpoolear.module_max_price_price_variance_max_extra'));
    }

    public function test_app_environment_is_available(): void
    {
        $this->assertNotSame('', app()->environment());
    }
}
