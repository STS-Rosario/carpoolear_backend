<?php

namespace Tests\Unit\Jobs;

use Database\Seeders\DonationTierSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use STS\Jobs\ReconcileClubSubscriptionInvoiceJob;
use STS\Models\DonationSubscription;
use STS\Models\DonationSubscriptionCharge;
use STS\Models\DonationTier;
use STS\Models\User;
use Tests\TestCase;

class ReconcileClubSubscriptionInvoiceJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DonationTierSeeder::class);
    }

    public function test_job_is_queued_with_retries_and_backoff(): void
    {
        $job = new ReconcileClubSubscriptionInvoiceJob('7032685777');

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame('7032685777', $job->invoiceId);
        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 120, 600, 1800, 7200], $job->backoff);
    }

    public function test_releases_when_invoice_still_has_no_nested_payment(): void
    {
        $invoiceId = '7032685777';
        $preapprovalId = 'eecd82e538c44ab5a3fe7b7ae76bde28';

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($invoiceId, $preapprovalId) {
            $mock->shouldReceive('getAuthorizedPayment')
                ->once()
                ->with($invoiceId)
                ->andReturn([
                    'id' => (int) $invoiceId,
                    'preapproval_id' => $preapprovalId,
                    'status' => 'scheduled',
                    'transaction_amount' => 5000,
                ]);
        });

        $job = (new ReconcileClubSubscriptionInvoiceJob($invoiceId))->withFakeQueueInteractions();

        try {
            $this->app->call([$job, 'handle']);
            $this->fail('Expected the job to retry while the nested payment is missing.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($invoiceId, $e->getMessage());
        }

        $this->assertSame(0, DonationSubscriptionCharge::query()->count());
    }

    public function test_upserts_charge_when_nested_payment_appears(): void
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
        ]);

        $invoiceId = '7032685777';
        $nestedPaymentId = 181833469289;

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($invoiceId, $preapprovalId, $nestedPaymentId) {
            $mock->shouldReceive('getAuthorizedPayment')
                ->once()
                ->with($invoiceId)
                ->andReturn([
                    'id' => (int) $invoiceId,
                    'preapproval_id' => $preapprovalId,
                    'status' => 'processed',
                    'transaction_amount' => 5000,
                    'payment' => [
                        'id' => $nestedPaymentId,
                        'status' => 'approved',
                        'transaction_amount' => 5000,
                        'date_approved' => '2026-10-07T11:09:56.000-00:00',
                    ],
                ]);
        });

        $job = (new ReconcileClubSubscriptionInvoiceJob($invoiceId))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertNotFailed();
        $this->assertDatabaseHas('donation_subscription_charges', [
            'donation_subscription_id' => $subscription->id,
            'mp_payment_id' => (string) $nestedPaymentId,
            'status' => 'approved',
            'amount_cents' => 500000,
        ]);
        $this->assertNotNull($subscription->fresh()->last_charged_at);
    }

    public function test_does_not_retry_when_invoice_is_canceled_without_payment(): void
    {
        $invoiceId = '7032685999';

        $this->mock(\STS\Services\MercadoPagoService::class, function ($mock) use ($invoiceId) {
            $mock->shouldReceive('getAuthorizedPayment')
                ->once()
                ->with($invoiceId)
                ->andReturn([
                    'id' => (int) $invoiceId,
                    'preapproval_id' => 'canceled-preapproval',
                    'status' => 'cancelled',
                ]);
        });

        $job = (new ReconcileClubSubscriptionInvoiceJob($invoiceId))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        $job->assertNotFailed();
        $this->assertSame(0, DonationSubscriptionCharge::query()->count());
    }
}
