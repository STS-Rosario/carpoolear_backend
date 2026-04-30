<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use STS\Contracts\Logic\Social;
use STS\Contracts\WebpayNormalFlowClient;
use STS\Services\Logic\SocialManager;
use STS\Services\Webpay\TransbankSdkWebpayNormalFlowClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Social::class, SocialManager::class);
        $this->app->singleton(WebpayNormalFlowClient::class, TransbankSdkWebpayNormalFlowClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
