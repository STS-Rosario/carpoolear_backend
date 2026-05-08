<?php

namespace Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use STS\Http\Controllers\Api\v1\AuthController;
use STS\Http\Middleware\UserLoggin;
use STS\Jobs\SendPasswordResetEmail;
use STS\Models\User;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthControllerApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_constructor_registers_logged_middleware_for_logout_and_retoken_only(): void
    {
        $controller = new AuthController(
            Mockery::mock(UsersManager::class),
            Mockery::mock(DeviceManager::class)
        );

        $middlewares = $controller->getMiddleware();
        $logged = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);

        $loggedOptions = is_array($logged) ? ($logged['options'] ?? []) : ($logged->options ?? []);
        $this->assertSame(['logout', 'retoken'], $loggedOptions['only'] ?? []);
    }

    public function test_get_config_returns_public_client_configuration_json(): void
    {
        $response = $this->getJson('api/config');

        $response->assertOk();
        $this->assertSame(
            (int) config('carpoolear.donation_month_days'),
            (int) $response->json('donation.month_days')
        );
        $this->assertSame(
            (int) config('carpoolear.donation_trips_count'),
            (int) $response->json('donation.trips_count')
        );
        $this->assertArrayHasKey('banner', $response->json());
        $this->assertArrayHasKey('identity_validation_manual_qr_enabled', $response->json());
        $this->assertIsBool($response->json('identity_validation_manual_qr_enabled'));
        $this->assertArrayNotHasKey('qr_payment_pos_external_id', $response->json());
        $this->assertArrayNotHasKey('donation_month_days', $response->json());
        $this->assertArrayNotHasKey('donation_trips_count', $response->json());
        $this->assertArrayNotHasKey('donation_trips_offset', $response->json());
        $this->assertArrayNotHasKey('donation_trips_rated', $response->json());
        $this->assertArrayNotHasKey('donation_ammount_needed', $response->json());
        $this->assertArrayNotHasKey('banner_url', $response->json());
        $this->assertArrayNotHasKey('banner_image', $response->json());
        $this->assertArrayNotHasKey('identity_validation_new_users_date', $response->json());
        $this->assertArrayHasKey('maintenance', $response->json());
        $maintenance = $response->json('maintenance');
        $this->assertFalse($maintenance['enabled']);
        $this->assertNull($maintenance['mode']);
        $this->assertNull($maintenance['message']);
        $this->assertNull($maintenance['ends_at']);
        $this->assertSame(config('carpoolear.maintenance_admin_path'), $maintenance['admin_path']);
    }

    public function test_get_config_reflects_active_maintenance_payload(): void
    {
        app(\STS\Services\Maintenance\MaintenanceStateService::class)->applyManualActive(
            true,
            'flexible',
            'DB upgrade',
            null,
            'manual',
            null,
            null
        );

        $response = $this->getJson('api/config');

        $response->assertOk();
        $this->assertTrue($response->json('maintenance.enabled'));
        $this->assertSame('flexible', $response->json('maintenance.mode'));
        $this->assertSame('DB upgrade', $response->json('maintenance.message'));
        $this->assertNull($response->json('maintenance.ends_at'));

        app(\STS\Services\Maintenance\MaintenanceStateService::class)->applyManualActive(
            false,
            null,
            null,
            null,
            'manual',
            null,
            null
        );
    }

    public function test_get_config_uses_cordova_banner_urls_when_old_webview_headers_present(): void
    {
        config([
            'carpoolear.banner_url' => 'https://banners.example/app',
            'carpoolear.banner_url_cordova' => 'https://banners.example/cordova',
            'carpoolear.banner_url_mobile' => 'https://banners.example/app-mobile',
            'carpoolear.banner_url_cordova_mobile' => 'https://banners.example/cordova-mobile',
            'carpoolear.banner_image' => 'app-image.png',
            'carpoolear.banner_image_cordova' => 'cordova-image.png',
            'carpoolear.banner_image_mobile' => 'app-image-mobile.png',
            'carpoolear.banner_image_cordova_mobile' => 'cordova-image-mobile.png',
        ]);

        // OldCordovaAppHelper reads superglobals (same as production PHP SAPI), not only the Request bag.
        $previous = [
            'HTTP_SEC_CH_UA' => $_SERVER['HTTP_SEC_CH_UA'] ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'HTTP_X_APP_PLATFORM' => $_SERVER['HTTP_X_APP_PLATFORM'] ?? null,
            'HTTP_X_APP_VERSION' => $_SERVER['HTTP_X_APP_VERSION'] ?? null,
        ];
        $_SERVER['HTTP_SEC_CH_UA'] = '"Chromium";v="118", "Android WebView";v="118"';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0 Mobile Safari/537.36';
        unset($_SERVER['HTTP_X_APP_PLATFORM'], $_SERVER['HTTP_X_APP_VERSION']);

        try {
            $response = $this->getJson('api/config');
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
        }

        $response->assertOk();
        $response->assertJsonPath('banner.url', 'https://banners.example/cordova');
        $response->assertJsonPath('banner.url_mobile', 'https://banners.example/cordova-mobile');
        $response->assertJsonPath('banner.image', 'cordova-image.png');
        $response->assertJsonPath('banner.image_mobile', 'cordova-image-mobile.png');
    }

    public function test_get_config_uses_non_cordova_banner_values_by_default(): void
    {
        config([
            'carpoolear.banner_url' => 'https://banners.example/app',
            'carpoolear.banner_url_cordova' => 'https://banners.example/cordova',
            'carpoolear.banner_url_mobile' => 'https://banners.example/app-mobile',
            'carpoolear.banner_url_cordova_mobile' => 'https://banners.example/cordova-mobile',
            'carpoolear.banner_image' => 'app-image.png',
            'carpoolear.banner_image_cordova' => 'cordova-image.png',
            'carpoolear.banner_image_mobile' => 'app-image-mobile.png',
            'carpoolear.banner_image_cordova_mobile' => 'cordova-image-mobile.png',
        ]);

        $response = $this->getJson('api/config');

        $response->assertOk();
        $response->assertJsonPath('banner.url', 'https://banners.example/app');
        $response->assertJsonPath('banner.url_mobile', 'https://banners.example/app-mobile');
        $response->assertJsonPath('banner.image', 'app-image.png');
        $response->assertJsonPath('banner.image_mobile', 'app-image-mobile.png');
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $user = User::factory()->create();

        $this->postJson('api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_credentials']);
    }

    public function test_login_success_returns_token_and_config_envelope(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);

        $response = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'config' => ['donation']]);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_logout_without_session_returns_unauthorized(): void
    {
        $this->postJson('api/logout')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_logout_with_valid_session_returns_ok(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $login = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk();

        $token = $login->json('token');

        $response = $this->postJson('api/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('OK', $response->getContent());
    }

    public function test_logout_with_valid_session_logs_successful_invalidation(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $login = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk();

        $token = $login->json('token');

        $this->postJson('api/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        Log::shouldHaveReceived('info')->with('JWT token invalidated successfully');
    }

    public function test_retoken_without_bearer_token_returns_unauthorized(): void
    {
        $this->postJson('api/retoken')->assertUnauthorized();
    }

    public function test_retoken_with_valid_token_returns_token_and_config(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $token = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');

        $this->postJson('api/retoken', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'config']);
    }

    public function test_reset_password_validation_requires_email(): void
    {
        $this->postJson('api/reset-password', [])
            ->assertStatus(422);
    }

    public function test_reset_password_for_unknown_email_returns_unprocessable(): void
    {
        $this->postJson('api/reset-password', [
            'email' => 'missing-user-'.uniqid('', true).'@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'user_not_found');
    }

    public function test_reset_password_for_known_user_returns_ok_and_queues_email(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->postJson('api/reset-password', ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        Queue::assertPushed(SendPasswordResetEmail::class);
    }

    public function test_change_password_with_valid_token_updates_password(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->postJson('api/reset-password', ['email' => $user->email])->assertOk();

        $row = DB::table('password_resets')->where('email', $user->email)->first();
        $this->assertNotNull($row);

        $this->postJson('api/change-password/'.$row->token, [
            'password' => 'abcdef99',
            'password_confirmation' => 'abcdef99',
        ])
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertTrue(Hash::check('abcdef99', $user->fresh()->password));
    }

    public function test_activate_with_invalid_token_returns_unprocessable(): void
    {
        $this->postJson('api/activate/not-a-real-activation-token')
            ->assertStatus(422);
    }

    public function test_activate_with_valid_token_returns_jwt_and_activates_user(): void
    {
        $token = 'act-'.uniqid('', true);
        $user = User::factory()->create([
            'active' => false,
            'activation_token' => $token,
        ]);

        $this->postJson('api/activate/'.$token)
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->assertTrue($user->fresh()->active);
        $this->assertNull($user->fresh()->activation_token);
    }

    public function test_log_endpoint_returns_success(): void
    {
        $this->postJson('api/log')->assertOk();
    }

    public function test_login_config_banner_uses_default_non_cordova_branch_from_get_config_helper(): void
    {
        config([
            'carpoolear.banner_url' => 'https://banners.example/default',
            'carpoolear.banner_url_cordova' => 'https://banners.example/cordova-only',
        ]);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);

        $response = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('config.banner.url', 'https://banners.example/default');

        $this->assertNotSame(
            'https://banners.example/cordova-only',
            $response->json('config.banner.url'),
            'Login must use the non-Cordova banner branch when _getConfig() is called without Cordova detection.'
        );
    }

    public function test_get_config_logs_environment_check_and_guest_warning(): void
    {
        Log::spy();

        $this->getJson('api/config')->assertOk();

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context): bool {
            if ((string) $message !== 'Environment Check:' || ! is_array($context)) {
                return false;
            }
            $raw = $context['raw_env'] ?? null;

            return is_array($raw)
                && array_key_exists('MODULE_USER_REQUEST_LIMITED_ENABLED', $raw)
                && array_key_exists('MODULE_USER_REQUEST_LIMITED_HOURS_RANGE', $raw)
                && isset($context['config_values'], $context['app_env'], $context['env_path']);
        });

        Log::shouldHaveReceived('warning')->with('getConfig called without authenticated user');
    }

    public function test_get_config_merges_flat_carpoolear_keys_after_exclude_foreach(): void
    {
        $this->getJson('api/config')
            ->assertOk()
            ->assertJsonPath('name_app', config('carpoolear.name_app'));
    }

    public function test_get_config_identity_validation_manual_qr_enabled_matches_conjunctive_gate(): void
    {
        $snapshot = [
            'services.mercadopago' => config('services.mercadopago'),
            'carpoolear.identity_validation_manual_enabled' => config('carpoolear.identity_validation_manual_enabled'),
            'carpoolear.identity_validation_manual_qr_enabled' => config('carpoolear.identity_validation_manual_qr_enabled'),
            'carpoolear.qr_payment_pos_external_id' => config('carpoolear.qr_payment_pos_external_id'),
        ];

        try {
            $mercado = config('services.mercadopago', []);
            $mercado['qr_payment_access_token'] = 'mp-qr-token';
            config(['services.mercadopago' => $mercado]);
            config([
                'carpoolear.identity_validation_manual_enabled' => true,
                'carpoolear.identity_validation_manual_qr_enabled' => true,
                'carpoolear.qr_payment_pos_external_id' => 'pos-external-1',
            ]);
            $this->getJson('api/config')->assertOk()->assertJsonPath('identity_validation_manual_qr_enabled', true);

            config(['carpoolear.identity_validation_manual_enabled' => false]);
            $this->getJson('api/config')->assertOk()->assertJsonPath('identity_validation_manual_qr_enabled', false);

            config([
                'carpoolear.identity_validation_manual_enabled' => true,
                'carpoolear.identity_validation_manual_qr_enabled' => false,
            ]);
            $this->getJson('api/config')->assertOk()->assertJsonPath('identity_validation_manual_qr_enabled', false);

            $mercado['qr_payment_access_token'] = '';
            config(['services.mercadopago' => $mercado]);
            config([
                'carpoolear.identity_validation_manual_enabled' => true,
                'carpoolear.identity_validation_manual_qr_enabled' => true,
            ]);
            $this->getJson('api/config')->assertOk()->assertJsonPath('identity_validation_manual_qr_enabled', false);

            $mercado['qr_payment_access_token'] = 'mp-qr-token';
            config(['services.mercadopago' => $mercado]);
            config(['carpoolear.qr_payment_pos_external_id' => '']);
            $this->getJson('api/config')->assertOk()->assertJsonPath('identity_validation_manual_qr_enabled', false);
        } finally {
            config($snapshot);
        }
    }

    public function test_login_with_banned_user_returns_user_banned_message(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => true,
        ]);

        $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])
            ->assertStatus(401)
            ->assertSee('user_banned');
    }

    public function test_login_with_inactive_user_returns_user_not_active_message(): void
    {
        $user = User::factory()->create([
            'active' => false,
            'banned' => false,
        ]);

        $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])
            ->assertStatus(401)
            ->assertSee('user_not_active');
    }

    public function test_retoken_with_app_version_passes_session_payload_to_device_manager(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $token = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');

        $device = Mockery::mock(DeviceManager::class)->makePartial();
        $device->shouldReceive('updateBySession')
            ->once()
            ->withArgs(function (string $sessionId, array $payload) use ($token): bool {
                return $sessionId !== ''
                    && ($payload['app_version'] ?? null) === '3.2.1'
                    && ($payload['session_id'] ?? null) === $token;
            });
        $this->instance(DeviceManager::class, $device);

        $this->postJson('api/retoken', ['app_version' => '3.2.1'], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();
    }

    public function test_retoken_after_short_ttl_expiry_refreshes_token_and_returns_config(): void
    {
        $previousTtl = config('jwt.ttl');
        $this->freezeTime();

        try {
            config(['jwt.ttl' => 1]);

            $user = User::factory()->create(['active' => true, 'banned' => false]);

            $token = $this->postJson('api/login', [
                'email' => $user->email,
                'password' => '123456',
            ])->assertOk()->json('token');

            $this->travel(2)->minutes();

            $this->postJson('api/retoken', [], [
                'Authorization' => 'Bearer '.$token,
            ])
                ->assertOk()
                ->assertJsonStructure(['token', 'config']);
        } finally {
            config(['jwt.ttl' => $previousTtl]);
        }
    }

    public function test_reset_password_maps_rate_limit_exception_to_user_message(): void
    {
        Log::spy();

        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('resetPassword')
            ->once()
            ->with('rate-test@example.com')
            ->andThrow(new \Exception('upstream returned 450 rate limit'));
        $this->instance(UsersManager::class, $users);

        $this->postJson('api/reset-password', ['email' => 'rate-test@example.com'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Too many password reset attempts. Please try again later.',
            ]);

        Log::shouldHaveReceived('error')->withArgs(fn ($message): bool => str_contains((string) $message, 'Password reset error'));
    }

    public function test_reset_password_maps_exception_with_450_code_even_without_rate_keyword(): void
    {
        Log::spy();

        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('resetPassword')
            ->once()
            ->with('code450@example.com')
            ->andThrow(new \Exception('upstream rejected with code 450'));
        $this->instance(UsersManager::class, $users);

        $this->postJson('api/reset-password', ['email' => 'code450@example.com'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Too many password reset attempts. Please try again later.',
            ]);

        Log::shouldHaveReceived('error')->withArgs(fn ($message): bool => str_contains((string) $message, 'Password reset error'));
    }

    public function test_reset_password_maps_exception_with_rate_keyword_even_without_450_code(): void
    {
        Log::spy();

        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('resetPassword')
            ->once()
            ->with('ratetext@example.com')
            ->andThrow(new \Exception('gateway rate limiting active'));
        $this->instance(UsersManager::class, $users);

        $this->postJson('api/reset-password', ['email' => 'ratetext@example.com'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Too many password reset attempts. Please try again later.',
            ]);

        Log::shouldHaveReceived('error')->withArgs(fn ($message): bool => str_contains((string) $message, 'Password reset error'));
    }

    public function test_reset_password_does_not_map_wait_only_messages_without_minutes(): void
    {
        Log::spy();

        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('resetPassword')
            ->once()
            ->with('waitonly@example.com')
            ->andThrow(new \Exception('Please wait before trying again'));
        $this->instance(UsersManager::class, $users);

        $this->postJson('api/reset-password', ['email' => 'waitonly@example.com'])
            ->assertStatus(500);

        Log::shouldHaveReceived('error')->withArgs(fn ($message): bool => str_contains((string) $message, 'Password reset error'));
    }

    public function test_reset_password_maps_wait_minutes_exception_to_same_message(): void
    {
        Log::spy();

        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('resetPassword')
            ->once()
            ->with('cooldown@example.com')
            ->andThrow(new \Exception('Please wait 4 minutes before requesting another password reset'));
        $this->instance(UsersManager::class, $users);

        $this->postJson('api/reset-password', ['email' => 'cooldown@example.com'])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Please wait 4 minutes before requesting another password reset',
            ]);

        Log::shouldHaveReceived('error')->withArgs(fn ($message): bool => str_contains((string) $message, 'Password reset error'));
    }

    public function test_login_returns_500_when_jwt_attempt_throws_jwt_exception(): void
    {
        $jwt = Mockery::mock();
        $jwt->shouldReceive('attempt')
            ->once()
            ->andThrow(new JWTException('token factory unavailable'));

        $original = $this->app->make('tymon.jwt.auth');
        JWTAuth::swap($jwt);

        try {
            $user = User::factory()->create([
                'active' => true,
                'banned' => false,
            ]);

            $this->postJson('api/login', [
                'email' => $user->email,
                'password' => '123456',
            ])
                ->assertStatus(500)
                ->assertJson(['error' => 'could_not_create_token']);
        } finally {
            JWTAuth::swap($original);
        }
    }

    public function test_retoken_with_banned_user_returns_forbidden_banned_payload(): void
    {
        $this->withoutMiddleware(UserLoggin::class);

        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);

        $token = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');

        $user->forceFill(['banned' => true])->save();

        $retoken = $this->postJson('api/retoken', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $retoken->assertForbidden();
        $this->assertSame('banned', $retoken->json());
    }

    public function test_logout_logs_error_when_jwt_invalidate_throws(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $devices = Mockery::mock(DeviceManager::class);
        $devices->shouldReceive('logoutDevice')->once();

        $tokenObj = Mockery::mock(\Tymon\JWTAuth\Token::class);

        $jwt = Mockery::mock();
        $jwt->shouldReceive('getToken')->twice()->andReturn($tokenObj, $tokenObj);
        $jwt->shouldReceive('invalidate')
            ->once()
            ->andThrow(new \RuntimeException('invalidate simulated failure'));

        $original = $this->app->make('tymon.jwt.auth');
        JWTAuth::swap($jwt);

        try {
            $this->actingAs($user, 'api');

            $controller = new AuthController(app(UsersManager::class), $devices);
            $response = $controller->logout(Request::create('http://localhost/api/logout', 'POST'));

            $this->assertSame(200, $response->getStatusCode());
        } finally {
            JWTAuth::swap($original);
        }

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context = []): bool {
            return str_contains((string) $message, 'Failed to invalidate JWT token')
                && str_contains((string) $message, 'invalidate simulated failure');
        });
    }
}
