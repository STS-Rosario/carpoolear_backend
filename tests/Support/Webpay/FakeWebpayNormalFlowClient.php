<?php

namespace Tests\Support\Webpay;

use STS\Contracts\WebpayNormalFlowClient;

/**
 * In-memory Webpay client for {@see \Tests\Feature\Http\PaymentControllerWebTest}.
 */
final class FakeWebpayNormalFlowClient implements WebpayNormalFlowClient
{
    public ?object $lastInit = null;

    public mixed $transactionResult = null;

    public function initTransaction(int $amount, string $buyOrder, string $sessionId, string $returnUrl, string $finalUrl): object
    {
        $this->lastInit = (object) [
            'amount' => $amount,
            'buyOrder' => $buyOrder,
            'sessionId' => $sessionId,
            'returnUrl' => $returnUrl,
            'finalUrl' => $finalUrl,
        ];

        $r = new \stdClass;
        $r->url = 'https://webpay.test-wsp/redirect';
        $r->token = 'unit-test-token';

        return $r;
    }

    public function getTransactionResult(?string $tokenWs): ?object
    {
        if ($this->transactionResult === null) {
            return null;
        }

        return is_object($this->transactionResult) ? $this->transactionResult : null;
    }
}
