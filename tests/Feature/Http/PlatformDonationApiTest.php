<?php

namespace Tests\Feature\Http;

use Database\Seeders\DonationTierSeeder;
use MercadoPago\Resources\Preference;
use STS\Http\Middleware\UserAdmin;
use STS\Models\DonationPayment;
use STS\Models\DonationTier;
use STS\Models\User;
use Tests\TestCase;

class PlatformDonationApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DonationTierSeeder::class);
        config(['carpoolear.platform_donations_api_enabled' => true]);
        config(['carpoolear.frontend_url' => 'https://app.carpoolear.test']);
        config(['services.mercadopago.access_token' => 'test-access-token']);
    }

    public function test_donation_tiers_are_publicly_listed(): void
    {
        $response = $this->getJson('/api/donation-tiers');

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonFragment(['slug' => 'cafe', 'amount' => 5000]);
    }

    public function test_checkout_once_requires_authentication(): void
    {
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();

        $this->postJson('/api/donations/checkout/once', ['tier_id' => $tier->id])
            ->assertUnauthorized();
    }

    public function test_checkout_once_returns_init_point_when_mp_is_stubbed(): void
    {
        $user = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) {
            $preference = new Preference;
            $preference->id = 'pref-123';
            $preference->init_point = 'https://mp.test/checkout';
            $mock->shouldReceive('createPaymentPreferenceForPlatformDonation')
                ->once()
                ->andReturn($preference);
            $mock->shouldReceive('createHashedExternalReferenceForPlatformDonation')
                ->once()
                ->andReturn('hash:encoded');
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/donations/checkout/once', [
                'tier_id' => $tier->id,
                'source' => 'after_rating',
                'trip_id' => 99,
            ]);

        $response->assertOk()
            ->assertJson([
                'init_point' => 'https://mp.test/checkout',
            ]);

        $this->assertDatabaseHas('donation_payments', [
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'status' => 'pending',
            'source' => 'after_rating',
            'trip_id' => 99,
        ]);
    }

    public function test_checkout_monthly_creates_pending_subscription(): void
    {
        $user = User::factory()->create();
        $tier = DonationTier::where('slug', 'beer')->firstOrFail();
        $tier->update(['mp_preapproval_plan_id' => 'plan-test-123']);

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createHashedExternalReferenceForPlatformDonation')
                ->once()
                ->andReturn('hash:encoded-monthly');
            $mock->shouldReceive('createPreapprovalCheckoutUrl')
                ->once()
                ->andReturn('https://mp.test/subscription');
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/donations/checkout/monthly', [
                'amount' => 7500,
                'source' => 'trips',
            ]);

        $response->assertOk()
            ->assertJson(['init_point' => 'https://mp.test/subscription']);

        $this->assertDatabaseHas('donation_subscriptions', [
            'user_id' => $user->id,
            'status' => 'pending',
            'source' => 'trips',
        ]);
    }

    public function test_admin_donation_summary_returns_totals(): void
    {
        $this->withoutMiddleware(UserAdmin::class);
        $admin = User::factory()->create(['is_admin' => true]);
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();

        DonationPayment::create([
            'user_id' => $admin->id,
            'donation_tier_id' => $tier->id,
            'amount_cents' => 500000,
            'status' => 'approved',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/admin/donations/summary')
            ->assertOk()
            ->assertJsonFragment(['one_time_total_cents' => 500000]);
    }
}
