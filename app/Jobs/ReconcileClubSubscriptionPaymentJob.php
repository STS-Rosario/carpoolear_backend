<?php

namespace STS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use STS\Services\MercadoPagoService;
use STS\Services\PlatformDonationService;

class ReconcileClubSubscriptionPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800, 7200];

    public function __construct(public string $paymentId) {}

    public function handle(MercadoPagoService $mercadoPagoService, PlatformDonationService $platformDonationService): void
    {
        $mpPayment = $mercadoPagoService->getPayment($this->paymentId);
        if ($mpPayment === null) {
            throw new RuntimeException("Club subscription payment {$this->paymentId} not found");
        }

        $status = strtolower((string) ($mpPayment['status'] ?? ''));
        if (in_array($status, ['rejected', 'cancelled', 'canceled'], true)) {
            Log::info('Club subscription payment is terminal without invoice', [
                'payment_id' => $this->paymentId,
                'status' => $status,
            ]);

            return;
        }

        if ($platformDonationService->handleClubSubscriptionPaymentViaInvoiceSearch($mpPayment, $this->paymentId)) {
            return;
        }

        throw new RuntimeException("Club subscription payment {$this->paymentId} invoice search still empty");
    }
}
