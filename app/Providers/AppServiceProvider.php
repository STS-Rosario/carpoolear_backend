<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use STS\Contracts\Logic\Social;
use STS\Services\Logic\SocialManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Social::class, SocialManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
