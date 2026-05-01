<?php

namespace Tests\Unit\Services\Webpay;

use STS\Services\Webpay\TransbankSdkWebpayNormalFlowClient;
use Tests\TestCase;

class TransbankSdkWebpayNormalFlowClientTest extends TestCase
{
    public function test_get_transaction_result_returns_null_for_null_blank_or_whitespace_token(): void
    {
        $client = new TransbankSdkWebpayNormalFlowClient;

        $this->assertNull($client->getTransactionResult(null));
        $this->assertNull($client->getTransactionResult(''));
        $this->assertNull($client->getTransactionResult("  \t\n"));
    }
}
