<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscription;
use STS\Models\DonationSubscriptionCharge;
use STS\Services\MercadoPagoService;
use STS\Services\PlatformDonationService;

class ReconcilePlatformDonations extends Command
{
    protected $signature = 'donations:reconcile {--days=2} {--subscription-days=30}';

    protected $description = 'Reconcile recent Mercado Pago platform donation payments';

    public function handle(MercadoPagoService $mercadoPagoService, PlatformDonationService $platformDonationService): int
    {
        $since = Carbon::now()->subDays((int) $this->option('days'));

        $pendingPayments = DonationPayment::query()
            ->where('status', 'pending')
            ->where('created_at', '>=', $since)
            ->whereNotNull('mp_preference_id')
            ->get();

        $reconciled = 0;
        foreach ($pendingPayments as $payment) {
            if (empty($payment->mp_payment_id)) {
                continue;
            }

            $mpPayment = $mercadoPagoService->getPayment($payment->mp_payment_id);
            if ($mpPayment) {
                $platformDonationService->handlePlatformPayment($mpPayment);
                $reconciled++;
            }
        }

        $pendingCharges = DonationSubscriptionCharge::query()
            ->where('status', 'pending')
            ->where('created_at', '>=', $since)
            ->get();

        foreach ($pendingCharges as $charge) {
            $mpPayment = $mercadoPagoService->getPayment($charge->mp_payment_id);
            if ($mpPayment) {
                $platformDonationService->handleSubscriptionAuthorizedPayment($mpPayment);
                $reconciled++;
            }
        }

        $subscriptionSince = Carbon::now()->subDays((int) $this->option('subscription-days'));
        $backfilled = 0;

        $authorizedWithoutCharges = DonationSubscription::query()
            ->where('status', 'authorized')
            ->whereNotNull('mp_preapproval_id')
            ->where('mp_preapproval_id', '!=', '')
            ->where('updated_at', '>=', $subscriptionSince)
            ->whereDoesntHave('charges', function ($query) {
                $query->where('status', 'approved');
            })
            ->get();

        foreach ($authorizedWithoutCharges as $subscription) {
            $invoices = $mercadoPagoService->searchAuthorizedPayments([
                'preapproval_id' => $subscription->mp_preapproval_id,
            ]);

            foreach ($invoices as $invoice) {
                $payload = $platformDonationService->mapAuthorizedPaymentInvoiceToChargePayload($invoice);
                if ($payload === null) {
                    continue;
                }

                $platformDonationService->handleSubscriptionAuthorizedPayment($payload);
                $backfilled++;
            }
        }

        $this->info("Reconciled {$reconciled} platform donation record(s).");
        $this->info("Backfilled {$backfilled} Club subscription charge(s).");

        return self::SUCCESS;
    }
}
