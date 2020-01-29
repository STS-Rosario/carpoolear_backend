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

        $this->app->bind('\STS\Contracts\Repository\Social', '\STS\Repository\SocialRepository');
        $this->app->bind('\STS\Contracts\Logic\Social', '\STS\Services\Logic\SocialManager');

        $this->app->bind('\STS\Contracts\Repository\Conversations', '\STS\Repository\ConversationRepository');
        $this->app->bind('\STS\Contracts\Repository\Messages', '\STS\Repository\MessageRepository');
        $this->app->bind('\STS\Contracts\Logic\Conversation', '\STS\Services\Logic\ConversationsManager');
        $this->app->bind('\STS\Contracts\Repository\Trip', '\STS\Repository\TripRepository');
        $this->app->bind('\STS\Contracts\Logic\Trip', '\STS\Services\Logic\TripsManager');

        $this->app->bind('\STS\Contracts\Repository\Car', '\STS\Repository\CarsRepository');
        $this->app->bind('\STS\Contracts\Logic\Car', '\STS\Services\Logic\CarsManager');

        $this->app->bind('\STS\Contracts\Repository\IPassengersRepository', '\STS\Repository\PassengersRepository');
        $this->app->bind('\STS\Contracts\Logic\IPassengersLogic', '\STS\Services\Logic\PassengersManager');

        $this->app->bind('\STS\Contracts\Repository\INotification', 'STS\Repository\NotificationRepository');
        $this->app->bind('\STS\Contracts\Logic\INotification', '\STS\Services\Logic\NotificationManager');

        $this->app->bind('\STS\Contracts\Repository\IRatingRepository', 'STS\Repository\RatingRepository');
        $this->app->bind('\STS\Contracts\Logic\IRateLogic', '\STS\Services\Logic\RatingManager');

        $this->app->bind('\STS\Contracts\Repository\Subscription', 'STS\Repository\SubscriptionsRepository');
        $this->app->bind('\STS\Contracts\Logic\Subscription', '\STS\Services\Logic\SubscriptionsManager');

        $this->app->bind('\STS\Contracts\Repository\Routes', 'STS\Repository\RoutesRepository');
        $this->app->bind('\STS\Contracts\Logic\Routes', '\STS\Services\Logic\RoutesManager');

        $this->app->bind('\STS\Contracts\Repository\IReferencesRepository', 'STS\Repository\ReferencesRepository');
        $this->app->bind('\STS\Contracts\Logic\IReferencesLogic', '\STS\Services\Logic\ReferencesManager');
    }
}
