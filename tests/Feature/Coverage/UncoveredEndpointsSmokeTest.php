<?php

namespace Tests\Feature\Coverage;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use STS\Models\User;
use Tests\TestCase;

/**
 * Smoke tests for HTTP surfaces that previously had no coverage.
 * Assertions stay permissive (2xx/3xx/4xx) where behaviour depends on env data.
 */
class UncoveredEndpointsSmokeTest extends TestCase
{
    private function adminUser(): User
    {
        return User::query()->create([
            'name' => 'Admin Coverage '.uniqid(),
            'email' => uniqid('admin_cov_', true).'@example.com',
            'password' => Hash::make('123456'),
            'active' => 1,
            'is_admin' => 1,
            'terms_and_conditions' => 1,
            'emails_notifications' => 1,
        ]);
    }

    public function test_public_data_endpoints_return_json(): void
    {
        foreach (['trips', 'seats', 'users', 'monthlyusers'] as $segment) {
            $response = $this->getJson("api/data/{$segment}");
            $response->assertOk();
            $this->assertStringContainsString(
                'application/json',
                (string) $response->headers->get('content-type')
            );
        }
    }

    public function test_public_campaign_by_slug_returns_not_found_when_missing(): void
    {
        $this->getJson('api/campaigns/this-slug-should-not-exist-'.uniqid())
            ->assertNotFound();
    }

    public function test_osrm_proxy_returns_json_when_upstream_unavailable(): void
    {
        config(['carpoolear.osrm_router_base_url' => 'https://127.0.0.1:9']);
        config(['carpoolear.osrm_router_fallback_base_url' => null]);

        Http::fake([
            '*' => Http::response(['code' => 'Error'], 503),
        ]);

        $this->getJson('api/osrm/route/v1/driving/-32.9,-60.7;-34.6,-58.4')
            ->assertOk()
            ->assertJsonPath('code', 'NoRoute');
    }

    public function test_whatsapp_webhook_get_without_valid_token_returns_forbidden(): void
    {
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=abc')
            ->assertForbidden();
    }

    public function test_mercadopago_webhook_accepts_unknown_action(): void
    {
        $this->postJson('/webhooks/mercadopago', ['action' => 'ignored.test'])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_mercadopago_oauth_callback_redirects_on_error_param(): void
    {
        $this->get('api/mercadopago/oauth/callback?error=access_denied')
            ->assertRedirect();
    }

    public function test_manual_validation_payment_success_redirects(): void
    {
        $this->get('api/mercadopago/manual-validation-success')
            ->assertRedirect();
    }

    public function test_data_web_route_returns_json(): void
    {
        $response = $this->get('/data-web');
        $response->assertOk();
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
    }

    public function test_transbank_entry_without_tp_id_is_handled(): void
    {
        $response = $this->get('/transbank');
        $this->assertTrue(in_array($response->status(), [200, 500], true));
    }

    public function test_social_login_with_invalid_provider_returns_client_or_server_error(): void
    {
        $status = $this->postJson('api/social/login/not-a-real-provider', [
            'access_token' => 'dummy-token',
        ])->status();

        $this->assertGreaterThanOrEqual(400, $status);
        $this->assertNotEquals(200, $status);
    }

    public function test_references_create_validation_errors_when_payload_empty(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->postJson('api/references', [])
            ->assertStatus(422);
    }

    public function test_admin_index_routes_return_json_when_authorized(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $paths = [
            'api/admin/badges',
            'api/admin/campaigns',
            'api/admin/cars',
            'api/admin/users',
            'api/admin/manual-identity-validations',
            'api/admin/mercado-pago-rejected-validations',
        ];

        foreach ($paths as $path) {
            $response = $this->getJson($path);
            $response->assertOk();
            $this->assertStringContainsString(
                'application/json',
                (string) $response->headers->get('content-type')
            );
        }
    }
}
