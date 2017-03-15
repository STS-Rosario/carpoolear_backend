<?php

namespace STS\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'STS\Events\User\Create' => ['STS\Listeners\User\CreateHandler'],
        'STS\Events\User\Update' => ['STS\Listeners\User\UpdateHandler'],
        'STS\Events\User\Reset' => ['STS\Listeners\Notification\ResetPasswordHandler'],
        'STS\Events\Friend\Request' => [
            'STS\Listeners\Notification\FriendRequest'
        ],
        'STS\Events\Friend\Accept' => [],
        'STS\Events\Friend\Reject' => [],

    ];

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);

        //
    }
}
