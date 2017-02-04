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
        $this->app->bind('\STS\Contracts\Logic\User', '\STS\Services\Logic\UsersManager');
        $this->app->bind('\STS\Contracts\Repository\User', '\STS\Repository\UserRepository');

        $this->app->bind('\STS\Contracts\Logic\Devices', '\STS\Services\Logic\DeviceManager');
        $this->app->bind('\STS\Contracts\Repository\Devices', '\STS\Repository\DeviceRepository');

        $this->app->bind('\STS\Contracts\Repository\Friends', '\STS\Repository\FriendsRepository');
        $this->app->bind('\STS\Contracts\Logic\Friends', '\STS\Services\Logic\FriendsManager');

        $this->app->bind('\STS\Contracts\Repository\Files', '\STS\Repository\FileRepository');
    }
}
