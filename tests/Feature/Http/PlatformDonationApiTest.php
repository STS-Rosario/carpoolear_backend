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
            ->assertJsonFragment(['slug' => 'cafe', 'amount' => 5000])
            ->assertJsonFragment(['slug' => 'beer', 'amount' => 7500])
            ->assertJsonFragment(['slug' => 'food', 'amount' => 12000]);
    }

    public function test_donation_tier_seeder_is_idempotent(): void
    {
        $this->seed(DonationTierSeeder::class);
        $this->seed(DonationTierSeeder::class);

        $this->assertSame(3, DonationTier::query()->count());
        $this->assertSame(500000, DonationTier::query()->where('slug', 'cafe')->value('amount_cents'));
    }

    public function test_database_seeder_registers_donation_tier_seeder(): void
    {
        $this->assertStringContainsString(
            'DonationTierSeeder::class',
            (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'))
        );
    }

    public function test_checkout_once_allows_anonymous_guest(): void
    {
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $this->stubOnceCheckoutMercadoPago();

        $response = $this->postJson('/api/donations/checkout/once', [
            'tier_id' => $tier->id,
            'source' => 'aportar',
        ]);

        $response->assertOk()
            ->assertJson(['init_point' => 'https://mp.test/checkout']);

        $this->assertDatabaseHas('donation_payments', [
            'user_id' => null,
            'donation_tier_id' => $tier->id,
            'status' => 'pending',
            'source' => 'aportar',
        ]);
    }

    public function test_checkout_once_guest_attaches_user_from_user_id(): void
    {
        $donor = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $this->stubOnceCheckoutMercadoPago();

        $this->postJson('/api/donations/checkout/once', [
            'tier_id' => $tier->id,
            'user_id' => $donor->id,
            'source' => 'aportar',
        ])->assertOk();

        $this->assertDatabaseHas('donation_payments', [
            'user_id' => $donor->id,
            'source' => 'aportar',
        ]);
    }

    public function test_checkout_once_guest_attaches_user_from_u_query(): void
    {
        $donor = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $this->stubOnceCheckoutMercadoPago();

        $this->postJson('/api/donations/checkout/once?u='.$donor->id, [
            'tier_id' => $tier->id,
            'source' => 'aportar',
        ])->assertOk();

        $this->assertDatabaseHas('donation_payments', [
            'user_id' => $donor->id,
            'source' => 'aportar',
        ]);
    }

    public function test_checkout_once_authenticated_ignores_spoofed_user_id(): void
    {
        $authUser = User::factory()->create();
        $other = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $this->stubOnceCheckoutMercadoPago();

        $this->actingAs($authUser, 'api')
            ->postJson('/api/donations/checkout/once', [
                'tier_id' => $tier->id,
                'user_id' => $other->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('donation_payments', [
            'user_id' => $authUser->id,
            'donation_tier_id' => $tier->id,
        ]);
        $this->assertDatabaseMissing('donation_payments', [
            'user_id' => $other->id,
        ]);
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
        $this->assertDatabaseMissing('donation_payments', [
            'user_id' => $user->id,
        ]);
    }

    public function test_checkout_monthly_guest_requires_a_user(): void
    {
        $this->postJson('/api/donations/checkout/monthly', [
            'amount' => 7500,
            'source' => 'aportar',
        ])->assertStatus(422);
    }

    public function test_checkout_monthly_guest_uses_user_id(): void
    {
        $donor = User::factory()->create();
        $tier = DonationTier::where('slug', 'beer')->firstOrFail();
        $tier->update(['mp_preapproval_plan_id' => 'plan-test-guest']);

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createHashedExternalReferenceForPlatformDonation')
                ->once()
                ->andReturn('hash:encoded-monthly-guest');
            $mock->shouldReceive('createPreapprovalCheckoutUrl')
                ->once()
                ->andReturn('https://mp.test/subscription');
        });

        $this->postJson('/api/donations/checkout/monthly', [
            'amount' => 7500,
            'user_id' => $donor->id,
            'source' => 'aportar',
        ])->assertOk();

        $this->assertDatabaseHas('donation_subscriptions', [
            'user_id' => $donor->id,
            'status' => 'pending',
            'source' => 'aportar',
        ]);
        $this->assertDatabaseMissing('donation_payments', [
            'user_id' => $donor->id,
        ]);
    }

    private function enableDonationQr(): void
    {
        config([
            'carpoolear.platform_donations_qr_enabled' => true,
            'services.mercadopago.qr_payment_access_token' => 'qr-token',
            'carpoolear.qr_payment_pos_external_id' => 'POS-1',
        ]);
    }

    private function stubOnceCheckoutMercadoPago(): void
    {
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
    }

    public function test_checkout_qr_order_returns_qr_payload_when_mp_is_stubbed(): void
    {
        $this->enableDonationQr();
        $user = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createQrOrderForPlatformDonation')
                ->once()
                ->andReturnUsing(function (DonationPayment $payment) {
                    return [
                        'payment_id' => $payment->id,
                        'order_id' => 'ord-donate',
                        'qr_data' => 'DONATE_QR',
                    ];
                });
        });

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/donations/checkout/qr-order', [
                'tier_id' => $tier->id,
                'source' => 'aportar',
                'trip_id' => 99,
            ]);

        $payment = DonationPayment::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($payment);

        $response->assertOk()
            ->assertJson([
                'payment_id' => $payment->id,
                'qr_data' => 'DONATE_QR',
                'order_id' => 'ord-donate',
            ]);

        $this->assertSame('pending', $payment->status);
        $this->assertSame('aportar', $payment->source);
        $this->assertSame(99, $payment->trip_id);
        $this->assertSame('donation_once_'.$payment->id, $payment->external_reference);
        $this->assertNull($payment->mp_preference_id);
    }

    public function test_checkout_qr_order_allows_anonymous_guest(): void
    {
        $this->enableDonationQr();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createQrOrderForPlatformDonation')
                ->once()
                ->andReturnUsing(function (DonationPayment $payment) {
                    return [
                        'payment_id' => $payment->id,
                        'order_id' => 'ord-guest',
                        'qr_data' => 'GUEST_QR',
                    ];
                });
        });

        $this->postJson('/api/donations/checkout/qr-order', [
            'tier_id' => $tier->id,
            'source' => 'aportar',
        ])->assertOk()->assertJson(['qr_data' => 'GUEST_QR']);

        $this->assertDatabaseHas('donation_payments', [
            'user_id' => null,
            'donation_tier_id' => $tier->id,
            'status' => 'pending',
            'source' => 'aportar',
        ]);
    }

    public function test_checkout_qr_order_is_unavailable_when_qr_flag_is_off(): void
    {
        config([
            'carpoolear.platform_donations_qr_enabled' => false,
            'services.mercadopago.qr_payment_access_token' => 'qr-token',
            'carpoolear.qr_payment_pos_external_id' => 'POS-1',
        ]);

        $this->postJson('/api/donations/checkout/qr-order', [
            'amount' => 5000,
            'source' => 'aportar',
        ])->assertStatus(503);
    }

    public function test_checkout_qr_order_is_unavailable_when_platform_donations_are_disabled(): void
    {
        $this->enableDonationQr();
        config(['carpoolear.platform_donations_api_enabled' => false]);

        $this->postJson('/api/donations/checkout/qr-order', [
            'amount' => 5000,
            'source' => 'aportar',
        ])->assertStatus(503);
    }

    public function test_donation_payment_status_returns_pending_payload(): void
    {
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $payment = DonationPayment::create([
            'donation_tier_id' => $tier->id,
            'amount_cents' => $tier->amount_cents,
            'status' => 'pending',
            'source' => 'aportar',
        ]);

        $this->getJson('/api/donations/payments/'.$payment->id)
            ->assertOk()
            ->assertExactJson([
                'payment_id' => $payment->id,
                'status' => 'pending',
            ]);
    }

    public function test_donation_payment_status_returns_approved_after_payment(): void
    {
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $payment = DonationPayment::create([
            'donation_tier_id' => $tier->id,
            'amount_cents' => $tier->amount_cents,
            'status' => 'approved',
            'source' => 'aportar',
            'paid_at' => now(),
        ]);

        $this->getJson('/api/donations/payments/'.$payment->id)
            ->assertOk()
            ->assertJson([
                'payment_id' => $payment->id,
                'status' => 'approved',
            ]);
    }

    public function test_donation_payment_status_returns_not_found_for_unknown_id(): void
    {
        $this->getJson('/api/donations/payments/999999')->assertNotFound();
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
