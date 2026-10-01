<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscriptionCharge;
use STS\Services\MercadoPagoService;
use STS\Services\PlatformDonationService;

class ReconcilePlatformDonations extends Command
{
    protected $signature = 'donations:reconcile {--days=2}';

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

        $this->info("Reconciled {$reconciled} platform donation record(s).");

        return self::SUCCESS;
    }
}
