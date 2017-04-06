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
        'STS\Events\User\Create'    => ['STS\Listeners\User\CreateHandler'],
        'STS\Events\User\Update'    => ['STS\Listeners\User\UpdateHandler'],
        'STS\Events\User\Reset'     => ['STS\Listeners\Notification\ResetPasswordHandler'],
        'STS\Events\Friend\Request' => [
            'STS\Listeners\Notification\FriendRequest',
        ],
        'STS\Events\Friend\Accept' => [],
        'STS\Events\Friend\Reject' => [],

        'STS\Events\Trip\Create' => [
            'STS\Listeners\DownloadStaticImage',
        ],
        'STS\Events\Trip\Update' => [
            'STS\Listeners\DownloadStaticImage',
        ],
        'STS\Events\Notification\NotificationSending' => [
            'STS\Listeners\Notification\CanSendEmail'
        ],
        'STS\Events\Passenger\Request' => [
            'STS\Listeners\Notification\PassengerRequest'
        ],
        'STS\Events\Passenger\Cancel' => [
            'STS\Listeners\Notification\PassengerCancel'
        ],
        'STS\Events\Passenger\Accept' => [
            'STS\Listeners\Notification\PassengerAccept'
        ],
        'STS\Events\Passenger\Reject' => [
            'STS\Listeners\Notification\PassengerReject'
        ],
        'STS\Events\Rating\PendingRate' => [
            'STS\Listeners\Notification\PendingRate'
        ],
    ];

    /**
     * Register any other events for your application.
     *
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     *
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);

        //
    }
}
