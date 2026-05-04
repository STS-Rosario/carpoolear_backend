<?php

namespace Tests\Unit\Providers;

use STS\Contracts\Logic\Social;
use STS\Contracts\SocialProvider;
use STS\Contracts\WebpayNormalFlowClient;
use STS\Services\Logic\SocialManager;
use STS\Services\Social\TestSocialProvider;
use STS\Services\Webpay\TransbankSdkWebpayNormalFlowClient;
use Tests\TestCase;

class AppServiceProviderBindingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(SocialProvider::class, fn () => new TestSocialProvider(json_encode([
            'provider_user_id' => 'app-service-provider-bindings-test',
        ])));
    }

    public function test_binds_social_contract_to_social_manager(): void
    {
        // Mutation intent: preserve `$this->app->bind(Social::class, SocialManager::class)` (~18 RemoveMethodCall).
        $this->assertTrue(
            $this->app->bound(Social::class),
            'AppServiceProvider must register the Social contract so the container can resolve SocialManager.'
        );

        $resolved = $this->app->make(Social::class);

        $this->assertInstanceOf(SocialManager::class, $resolved);
    }

    public function test_registers_webpay_normal_flow_client_singleton(): void
    {
        // Mutation intent: preserve `$this->app->singleton(WebpayNormalFlowClient::class, …)` (~19 RemoveMethodCall).
        $this->assertTrue(
            $this->app->bound(WebpayNormalFlowClient::class),
            'AppServiceProvider must register the WebpayNormalFlowClient binding.'
        );
        $this->assertTrue(
            $this->app->isShared(WebpayNormalFlowClient::class),
            'WebpayNormalFlowClient must be registered as a singleton (shared), not a transient binding.'
        );

        $a = $this->app->make(WebpayNormalFlowClient::class);
        $b = $this->app->make(WebpayNormalFlowClient::class);

        $this->assertInstanceOf(TransbankSdkWebpayNormalFlowClient::class, $a);
        $this->assertSame($a, $b);
    }
}
