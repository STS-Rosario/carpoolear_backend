<?php

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Http\Controllers\Api\v1\PhoneVerificationController;
use STS\Http\ExceptionWithErrors;
use STS\Models\User;
use STS\Services\Logic\PhoneVerificationManager;
use Tests\TestCase;

class PhoneVerificationControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_registers_logged_middleware_only_for_phone_actions(): void
    {
        $controller = new PhoneVerificationController(
            Mockery::mock(PhoneVerificationManager::class),
        );

        $logged = collect($controller->getMiddleware())->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);

        $options = is_array($logged) ? ($logged['options'] ?? []) : ($logged->options ?? []);
        $this->assertSame(['send', 'verify', 'resend', 'status'], $options['only'] ?? []);
    }

    public function test_send_returns_json_when_manager_returns_payload(): void
    {
        $user = User::factory()->create();
        $manager = Mockery::mock(PhoneVerificationManager::class);
        $manager->shouldReceive('sendVerificationCode')
            ->once()
            ->with($user, Mockery::type(Request::class))
            ->andReturn([
                'phone' => '+5491100000000',
                'expires_in_minutes' => 10,
            ]);

        $this->actingAs($user, 'api');
        Log::spy();

        $response = (new PhoneVerificationController($manager))->send(
            Request::create('/api/phone/send', 'POST', ['phone' => '+5491100000000'])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Verification code sent successfully',
            'phone' => '+5491100000000',
            'expires_in_minutes' => 10,
        ], $response->getData(true));

        Log::shouldHaveReceived('info')->withArgs(function (...$args) use ($user): bool {
            return count($args) === 2
                && $args[0] === 'Phone verification send request'
                && ($args[1]['user_id'] ?? null) === $user->id
                && ($args[1]['phone'] ?? null) === '+5491100000000'
                && isset($args[1]['ip'])
                && is_string($args[1]['ip']);
        })->once();

        Log::shouldHaveReceived('info')->withArgs(function (...$args) use ($user): bool {
            return count($args) === 2
                && $args[0] === 'Phone verification send successful'
                && ($args[1]['user_id'] ?? null) === $user->id
                && ($args[1]['phone'] ?? null) === '+5491100000000';
        })->once();
    }

    public function test_send_throws_exception_with_errors_and_logs_when_manager_returns_null(): void
    {
        $user = User::factory()->create();
        $manager = Mockery::mock(PhoneVerificationManager::class);
        $manager->shouldReceive('sendVerificationCode')->once()->andReturn(null);
        $manager->shouldReceive('getErrors')->once()->andReturn(['phone' => ['invalid']]);

        $this->actingAs($user, 'api');
        Log::spy();

        try {
            (new PhoneVerificationController($manager))->send(Request::create('/', 'POST', ['phone' => 'x']));
            $this->fail('Expected ExceptionWithErrors');
        } catch (ExceptionWithErrors $e) {
            $this->assertSame('Validation failed', $e->getMessage());
        }

        Log::shouldHaveReceived('info')->withArgs(function (...$args) use ($user): bool {
            return count($args) === 2
                && $args[0] === 'Phone verification send request'
                && ($args[1]['user_id'] ?? null) === $user->id
                && ($args[1]['phone'] ?? null) === 'x'
                && isset($args[1]['ip'])
                && is_string($args[1]['ip']);
        })->once();

        Log::shouldHaveReceived('error')->withArgs(function (...$args) use ($user): bool {
            return count($args) === 2
                && $args[0] === 'Phone verification send failed'
                && is_array($args[1])
                && ($args[1]['user_id'] ?? null) === $user->id
                && ($args[1]['phone'] ?? null) === 'x'
                && ($args[1]['errors'] ?? null) === ['phone' => ['invalid']];
        })->once();
    }

    public function test_verify_returns_json_when_manager_returns_payload(): void
    {
        $user = User::factory()->create();
        $manager = Mockery::mock(PhoneVerificationManager::class);
        $manager->shouldReceive('verifyPhoneNumber')
            ->once()
            ->with($user, Mockery::type(Request::class))
            ->andReturn([
                'phone_verified' => true,
                'phone_verified_at' => '2026-04-30T12:00:00Z',
                'phone' => '+5491100000001',
            ]);

        $this->actingAs($user, 'api');

        $response = (new PhoneVerificationController($manager))->verify(
            Request::create('/', 'POST', ['code' => '123456'])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Phone number verified successfully',
            'phone_verified' => true,
            'phone_verified_at' => '2026-04-30T12:00:00Z',
            'phone' => '+5491100000001',
        ], $response->getData(true));
    }

    public function test_verify_throws_exception_when_manager_returns_null(): void
    {
        $user = User::factory()->create();
        $manager = Mockery::mock(PhoneVerificationManager::class);
        $manager->shouldReceive('verifyPhoneNumber')->once()->andReturn(null);
        $manager->shouldReceive('getErrors')->once()->andReturn(['code' => ['wrong']]);

        $this->actingAs($user, 'api');

        $this->expectException(ExceptionWithErrors::class);

        (new PhoneVerificationController($manager))->verify(Request::create('/', 'POST', ['code' => '000000']));
    }

    public function test_resend_returns_json_when_manager_returns_payload(): void
    {
        $user = User::factory()->create();
        $manager = Mockery::mock(PhoneVerificationManager::class);
        $manager->shouldReceive('resendVerificationCode')
            ->once()
            ->with($user)
            ->andReturn(['expires_in_minutes' => 15]);

        $this->actingAs($user, 'api');

        $response = (new PhoneVerificationController($manager))->resend(Request::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Verification code resent successfully',
            'expires_in_minutes' => 15,
        ], $response->getData(true));
    }

    public function test_resend_throws_exception_when_manager_returns_null(): void
    {
        $user = User::factory()->create();
        $manager = Mockery::mock(PhoneVerificationManager::class);
        $manager->shouldReceive('resendVerificationCode')->once()->with($user)->andReturn(null);
        $manager->shouldReceive('getErrors')->once()->andReturn(['phone' => ['rate_limited']]);

        $this->actingAs($user, 'api');

        $this->expectException(ExceptionWithErrors::class);

        (new PhoneVerificationController($manager))->resend(Request::create('/'));
    }

    public function test_status_returns_manager_payload(): void
    {
        $user = User::factory()->create();
        $manager = Mockery::mock(PhoneVerificationManager::class);
        $manager->shouldReceive('getVerificationStatus')->once()->with($user)->andReturn([
            'phone_verified' => false,
            'has_pending_code' => true,
        ]);

        $this->actingAs($user, 'api');

        $response = (new PhoneVerificationController($manager))->status(Request::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'phone_verified' => false,
            'has_pending_code' => true,
        ], $response->getData(true));
    }
}
