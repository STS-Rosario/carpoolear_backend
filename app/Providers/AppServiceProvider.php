<?php

namespace STS\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
            $this->app->bind('\STS\Contracts\Logic\TripsLogic', '\STS\Services\Logic\TripsManager');
    }
}
