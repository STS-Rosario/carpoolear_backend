<?php

namespace Tests\Unit\Console\Commands;

use Database\Seeders\DonationTierSeeder;
use Illuminate\Support\Facades\Artisan;
use STS\Models\DonationSubscription;
use STS\Models\DonationSubscriptionCharge;
use STS\Models\DonationTier;
use STS\Models\User;
use Tests\TestCase;

class ReconcilePlatformDonationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DonationTierSeeder::class);
    }

    public function test_backfills_authorized_subscription_with_zero_approved_charges(): void
    {
        $user = User::factory()->create();
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $preapprovalId = 'eecd82e538c44ab5a3fe7b7ae76bde28';
        $subscription = DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'mp_preapproval_id' => $preapprovalId,
            'mp_preapproval_plan_id' => 'b1e39e90a1b84d759ff2a5a3cb636ac6',
            'status' => 'authorized',
            'transaction_amount_cents' => 500000,
            'last_charged_at' => null,
        ]);

        $nestedPaymentId = 181833469289;

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($preapprovalId, $nestedPaymentId) {
            $mock->shouldReceive('searchAuthorizedPayments')
                ->once()
                ->with(['preapproval_id' => $preapprovalId])
                ->andReturn([
                    [
                        'id' => 7032685777,
                        'preapproval_id' => $preapprovalId,
                        'status' => 'processed',
                        'transaction_amount' => 5000,
                        'payment' => [
                            'id' => $nestedPaymentId,
                            'status' => 'approved',
                            'transaction_amount' => 5000,
                            'date_approved' => '2026-10-07T11:09:56.000-00:00',
                        ],
                    ],
                ]);
        });

        Artisan::call('donations:reconcile', ['--subscription-days' => 30]);

        $this->assertDatabaseHas('donation_subscription_charges', [
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => (string) $nestedPaymentId,
            'status' => 'approved',
            'amount_cents' => 500000,
        ]);
        $this->assertNotNull($subscription->fresh()->last_charged_at);
        $this->assertStringContainsString('1', Artisan::output());
    }

    public function test_does_not_duplicate_existing_approved_charge(): void
    {
        $user = User::factory()->create();
        $tier = DonationTier::where('slug', 'beer')->firstOrFail();
        $preapprovalId = '0912ca427d744b05a8a44dc4c09df6e0';
        $subscription = DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'mp_preapproval_id' => $preapprovalId,
            'status' => 'authorized',
            'transaction_amount_cents' => 750000,
            'last_charged_at' => now()->subDays(2),
        ]);

        $mpPaymentId = '182326060495';
        DonationSubscriptionCharge::create([
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => $mpPaymentId,
            'amount_cents' => 750000,
            'status' => 'approved',
            'charged_at' => now()->subDays(2),
        ]);

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('searchAuthorizedPayments')->never();
        });

        Artisan::call('donations:reconcile', ['--subscription-days' => 30]);

        $this->assertSame(1, DonationSubscriptionCharge::query()->count());
        $this->assertDatabaseHas('donation_subscription_charges', [
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => $mpPaymentId,
            'status' => 'approved',
        ]);
    }
}
