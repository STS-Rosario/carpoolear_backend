<?php

namespace Tests\Unit\Providers;

use STS\Contracts\Logic\Social;
use STS\Contracts\WebpayNormalFlowClient;
use STS\Services\Logic\SocialManager;
use STS\Services\Webpay\TransbankSdkWebpayNormalFlowClient;
use Tests\TestCase;

class AppServiceProviderBindingsTest extends TestCase
{
    public function test_binds_social_contract_to_social_manager(): void
    {
        // Mutation intent: preserve `$this->app->bind(Social::class, SocialManager::class)` (~18 RemoveMethodCall).
        $resolved = $this->app->make(Social::class);

        $this->assertInstanceOf(SocialManager::class, $resolved);
    }

    public function test_registers_webpay_normal_flow_client_singleton(): void
    {
        // Mutation intent: preserve singleton registration (~19 RemoveMethodCall).
        $a = $this->app->make(WebpayNormalFlowClient::class);
        $b = $this->app->make(WebpayNormalFlowClient::class);

        $this->assertInstanceOf(TransbankSdkWebpayNormalFlowClient::class, $a);
        $this->assertSame($a, $b);
    }
}
