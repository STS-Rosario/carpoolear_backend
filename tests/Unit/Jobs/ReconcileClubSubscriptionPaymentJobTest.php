<?php

namespace Tests\Unit\Jobs;

use Database\Seeders\DonationTierSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use STS\Jobs\ReconcileClubSubscriptionPaymentJob;
use STS\Models\DonationSubscription;
use STS\Models\DonationSubscriptionCharge;
use STS\Models\DonationTier;
use STS\Models\User;
use Tests\TestCase;

class ReconcileClubSubscriptionPaymentJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DonationTierSeeder::class);
    }

    public function test_job_is_queued_with_retries_and_backoff(): void
    {
        $job = new ReconcileClubSubscriptionPaymentJob('181833469289');

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame('181833469289', $job->paymentId);
        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 120, 600, 1800, 7200], $job->backoff);
    }

    public function test_retries_when_invoice_search_is_still_empty(): void
    {
        $paymentId = '181833469289';

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($paymentId) {
            $mock->shouldReceive('getPayment')
                ->once()
                ->with($paymentId)
                ->andReturn([
                    'id' => (int) $paymentId,
                    'status' => 'approved',
                    'transaction_amount' => 5000.0,
                    'external_reference' => '',
                    'preapproval_id' => null,
                    'point_of_interaction' => ['type' => 'SUBSCRIPTIONS'],
                    'description' => 'Donación mensual Carpoolear - cafe',
                ]);
            $mock->shouldReceive('searchAuthorizedPayments')
                ->once()
                ->with(['payment_id' => $paymentId])
                ->andReturn([]);
        });

        $job = (new ReconcileClubSubscriptionPaymentJob($paymentId))->withFakeQueueInteractions();

        try {
            $this->app->call([$job, 'handle']);
            $this->fail('Expected the job to retry while invoice search is empty.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($paymentId, $e->getMessage());
        }

        $this->assertSame(0, DonationSubscriptionCharge::query()->count());
    }

    public function test_upserts_charge_when_invoice_search_finds_pending_subscription(): void
    {
        $user = User::factory()->create(['email' => 'diego-pending@example.test']);
        $tier = DonationTier::where('slug', 'cafe')->firstOrFail();
        $planId = 'b1e39e90a1b84d759ff2a5a3cb636ac6';
        $subscription = DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'mp_preapproval_plan_id' => $planId,
            'status' => 'pending',
            'transaction_amount_cents' => 500000,
            'mp_preapproval_id' => null,
        ]);

        $preapprovalId = 'eecd82e538c44ab5a3fe7b7ae76bde28';
        $paymentId = '181833469289';

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($paymentId, $preapprovalId, $planId, $user) {
            $mock->shouldReceive('getPayment')
                ->once()
                ->with($paymentId)
                ->andReturn([
                    'id' => (int) $paymentId,
                    'status' => 'approved',
                    'transaction_amount' => 5000.0,
                    'external_reference' => '',
                    'preapproval_id' => null,
                    'point_of_interaction' => ['type' => 'SUBSCRIPTIONS'],
                    'description' => 'Donación mensual Carpoolear - cafe',
                    'date_approved' => '2026-10-07T11:09:56.000-00:00',
                ]);
            $mock->shouldReceive('searchAuthorizedPayments')
                ->once()
                ->with(['payment_id' => $paymentId])
                ->andReturn([
                    [
                        'id' => 7032685777,
                        'preapproval_id' => $preapprovalId,
                        'payment' => [
                            'id' => (int) $paymentId,
                            'status' => 'approved',
                        ],
                    ],
                ]);
            $mock->shouldReceive('getPreapproval')
                ->once()
                ->with($preapprovalId)
                ->andReturn([
                    'id' => $preapprovalId,
                    'status' => 'authorized',
                    'preapproval_plan_id' => $planId,
                    'payer_email' => $user->email,
                    'auto_recurring' => ['transaction_amount' => 5000],
                ]);
        });

        $job = (new ReconcileClubSubscriptionPaymentJob($paymentId))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertNotFailed();
        $subscription->refresh();
        $this->assertSame($preapprovalId, $subscription->mp_preapproval_id);
        $this->assertSame('authorized', $subscription->status);
        $this->assertDatabaseHas('donation_subscription_charges', [
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => $paymentId,
            'status' => 'approved',
        ]);
    }

    public function test_does_not_retry_when_payment_is_rejected(): void
    {
        $paymentId = '181833469000';

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($paymentId) {
            $mock->shouldReceive('getPayment')
                ->once()
                ->with($paymentId)
                ->andReturn([
                    'id' => (int) $paymentId,
                    'status' => 'rejected',
                    'external_reference' => '',
                    'point_of_interaction' => ['type' => 'SUBSCRIPTIONS'],
                ]);
            $mock->shouldReceive('searchAuthorizedPayments')->never();
        });

        $job = (new ReconcileClubSubscriptionPaymentJob($paymentId))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertNotFailed();
        $this->assertSame(0, DonationSubscriptionCharge::query()->count());
    }
}
