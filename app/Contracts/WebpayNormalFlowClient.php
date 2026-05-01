<?php

namespace STS\Contracts;

/**
 * Webpay Plus "normal" (legacy flow) operations used by {@see \STS\Http\Controllers\PaymentController}.
 */
interface WebpayNormalFlowClient
{
    /**
     * @return object{url: string, token: string}
     */
    public function initTransaction(int $amount, string $buyOrder, string $sessionId, string $returnUrl, string $finalUrl): object;

    /**
     * @return object|null Legacy-shaped object with buyOrder, detailOutput->responseCode, urlRedirection, or null when invalid.
     */
    public function getTransactionResult(?string $tokenWs): ?object;
}
