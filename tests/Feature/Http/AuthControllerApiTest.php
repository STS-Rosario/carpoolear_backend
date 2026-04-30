<?php

namespace Tests\Feature\Http;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use STS\Http\Controllers\Api\v1\AuthController;
use STS\Jobs\SendPasswordResetEmail;
use STS\Models\User;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

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

    public function test_login_with_banned_user_returns_401(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => true,
        ]);

        $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertStatus(401);
    }

    public function test_login_with_inactive_user_returns_401(): void
    {
        $user = User::factory()->create([
            'active' => false,
            'banned' => false,
        ]);

        $this->postJson('api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertStatus(401);
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
}
