<?php

namespace Tests\Unit\Providers;

use STS\Contracts\Logic\Social;
use STS\Contracts\SocialProvider;
use STS\Services\Logic\SocialManager;
use STS\Services\Social\TestSocialProvider;
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
}
