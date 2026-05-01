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

        Log::shouldHaveReceived('info')->withArgs(function (...$args): bool {
            return count($args) === 2
                && $args[0] === 'Phone verification send successful'
                && is_array($args[1])
                && array_key_exists('user_id', $args[1])
                && array_key_exists('phone', $args[1]);
        });
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

        Log::shouldHaveReceived('error')->withArgs(function (...$args): bool {
            return count($args) === 2
                && $args[0] === 'Phone verification send failed'
                && is_array($args[1])
                && ($args[1]['errors'] ?? null) === ['phone' => ['invalid']];
        });
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
