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

class ReconcileClubSubscriptionInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800, 7200];

    public function __construct(public string $invoiceId) {}

    public function handle(MercadoPagoService $mercadoPagoService, PlatformDonationService $platformDonationService): void
    {
        $invoice = $mercadoPagoService->getAuthorizedPayment($this->invoiceId);
        if ($invoice === null) {
            throw new RuntimeException("Club subscription invoice {$this->invoiceId} not found");
        }

        $chargePayload = $platformDonationService->mapAuthorizedPaymentInvoiceToChargePayload($invoice);
        if ($chargePayload !== null) {
            $platformDonationService->handleSubscriptionAuthorizedPayment($chargePayload);

            return;
        }

        $status = strtolower((string) ($invoice['status'] ?? ''));
        if (in_array($status, ['cancelled', 'canceled'], true)) {
            Log::info('Club subscription invoice canceled without nested payment', [
                'invoice_id' => $this->invoiceId,
                'preapproval_id' => $invoice['preapproval_id'] ?? null,
            ]);

            return;
        }

        throw new RuntimeException("Club subscription invoice {$this->invoiceId} has no nested payment yet");
    }
}
