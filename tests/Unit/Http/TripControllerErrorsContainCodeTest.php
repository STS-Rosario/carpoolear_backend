<?php

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Mockery;
use ReflectionMethod;
use STS\Http\Controllers\Api\v1\TripController;
use STS\Repository\TripSearchRepository;
use STS\Services\Logic\TripsManager;
use Tests\TestCase;

class TripControllerErrorsContainCodeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function controller(): TripController
    {
        return new TripController(
            Request::create('/'),
            Mockery::mock(TripsManager::class),
            Mockery::mock(TripSearchRepository::class),
        );
    }

    public function test_errors_contain_code_returns_false_for_null_errors(): void
    {
        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->controller(), null, 'routing_service_unavailable'));
    }

    public function test_errors_contain_code_returns_false_when_error_key_missing(): void
    {
        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->controller(), ['other' => 'x'], 'routing_service_unavailable'));
    }

    public function test_errors_contain_code_matches_scalar_error_entry(): void
    {
        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->controller(), ['error' => 'routing_service_unavailable'], 'routing_service_unavailable'));
    }

    public function test_errors_contain_code_matches_array_error_list(): void
    {
        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->controller(), ['error' => ['routing_service_unavailable']], 'routing_service_unavailable'));
    }

    public function test_errors_contain_code_returns_false_when_code_not_in_list(): void
    {
        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->controller(), ['error' => ['trip_invalid_seats']], 'routing_service_unavailable'));
    }

    public function test_errors_contain_code_reads_message_bag_via_to_array(): void
    {
        $method = new ReflectionMethod(TripController::class, 'errorsContainCode');
        $method->setAccessible(true);

        $bag = new MessageBag;
        $bag->add('error', 'routing_service_unavailable');

        $this->assertTrue($method->invoke($this->controller(), $bag, 'routing_service_unavailable'));
    }

    public function test_message_for_trip_write_errors_returns_default_when_errors_null(): void
    {
        $method = new ReflectionMethod(TripController::class, 'messageForTripWriteErrors');
        $method->setAccessible(true);

        $this->assertSame(
            'Could not create new trip.',
            $method->invoke($this->controller(), null, 'Could not create new trip.')
        );
    }

    public function test_message_for_trip_write_errors_returns_translated_routing_message_when_present(): void
    {
        $method = new ReflectionMethod(TripController::class, 'messageForTripWriteErrors');
        $method->setAccessible(true);

        $expected = trans('errors.routing_service_unavailable');
        $this->assertSame(
            $expected,
            $method->invoke($this->controller(), ['error' => ['routing_service_unavailable']], 'Could not create new trip.')
        );
    }
}
