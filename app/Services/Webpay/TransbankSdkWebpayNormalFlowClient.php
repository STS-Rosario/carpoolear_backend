<?php

namespace STS\Services\Webpay;

use STS\Contracts\WebpayNormalFlowClient;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;

/**
 * Webpay Plus REST (Transbank SDK v4) adapter for the legacy controller flow.
 */
class TransbankSdkWebpayNormalFlowClient implements WebpayNormalFlowClient
{
    public function __construct(
        private readonly ?string $commerceCode = null,
        private readonly ?string $apiKey = null,
    ) {}

    public function initTransaction(int $amount, string $buyOrder, string $sessionId, string $returnUrl, string $finalUrl): object
    {
        $this->configure();

        $response = WebpayPlus::transaction()->create($buyOrder, $sessionId, $amount, $returnUrl);

        return (object) [
            'url' => (string) $response->getUrl(),
            'token' => (string) $response->getToken(),
        ];
    }

    public function getTransactionResult(?string $tokenWs): ?object
    {
        if ($tokenWs === null || trim($tokenWs) === '') {
            return null;
        }

        $this->configure();

        try {
            $commit = WebpayPlus::transaction()->commit($tokenWs);
        } catch (\Throwable) {
            return null;
        }

        $legacy = new \stdClass;
        $legacy->buyOrder = $commit->getBuyOrder();
        $legacy->urlRedirection = 'https://webpay3gint.transbank.cl/webpayserver/voucher.cgi';

        $detail = new \stdClass;
        $detail->responseCode = (int) $commit->getResponseCode();
        $legacy->detailOutput = $detail;

        return $legacy;
    }

    private function configure(): void
    {
        $code = $this->commerceCode ?? config('services.transbank.webpay_plus.commerce_code') ?: WebpayPlus::DEFAULT_COMMERCE_CODE;
        $key = $this->apiKey ?? config('services.transbank.webpay_plus.api_key') ?: Options::DEFAULT_API_KEY;

        WebpayPlus::configureForIntegration($code, $key);
    }
}
