<?php

namespace STS\Providers;

use STS\Listeners\Ratings\CreateRatingDeleteTrip;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'STS\Events\User\Create'    => [
            'STS\Listeners\User\CreateHandler',
        ],
        'STS\Events\User\Update'    => [
            'STS\Listeners\User\UpdateHandler',
        ],
        'STS\Events\User\Reset'     => [
            'STS\Listeners\Notification\ResetPasswordHandler',
        ],
        'STS\Events\Friend\Request' => [
            'STS\Listeners\Notification\FriendRequest',
        ],
        'STS\Events\Friend\Accept' => [
            'STS\Listeners\Notification\FriendAccept',
        ],
        'STS\Events\Friend\Reject' => [
            'STS\Listeners\Notification\FriendReject',
        ],
        'STS\Events\Friend\Cancel' => [
            'STS\Listeners\Notification\FriendCancel',
        ],
        'STS\Events\Trip\Create' => [
            'STS\Listeners\DownloadStaticImage',
            'STS\Listeners\Subscriptions\OnNewTrip',
            // 'STS\Listeners\Conversation\createConversation',
        ],
        'STS\Events\Trip\Update' => [
            'STS\Listeners\DownloadStaticImage',
            'STS\Listeners\Notification\UpdateTrip',
            'STS\Listeners\Subscriptions\OnNewTrip',
        ],
        'STS\Events\Trip\Delete' => [
            CreateRatingDeleteTrip::class,
        ],
        'STS\Events\Trip\Alert\HourLeft' => [
            'STS\Listeners\Notification\TripHourLeft',
        ],
        'STS\Events\Trip\Alert\RequestRemainder' => [
            'STS\Listeners\Notification\TripRequestRemainder',
        ],
        'STS\Events\Trip\Alert\RequestNotAnswer' => [
            'STS\Listeners\Notification\TripRequestNotAnswer',
        ],

        'STS\Events\Notification\NotificationSending' => [
            'STS\Listeners\Notification\CanSendEmail',
            'STS\Listeners\Notification\PreventMessageEmail',
        ],
        'STS\Events\Passenger\Request' => [
            'STS\Listeners\Notification\PassengerRequest',
        ],
        'STS\Events\Passenger\Cancel' => [
            'STS\Listeners\Notification\PassengerCancel',
            // 'STS\Listeners\Conversation\removeUserConversation',
        ],
        'STS\Events\Passenger\Accept' => [
            'STS\Listeners\Notification\PassengerAccept',
            // 'STS\Listeners\Conversation\addUserConversation',
        ],
        'STS\Events\Passenger\Reject' => [
            'STS\Listeners\Notification\PassengerReject',
        ],
        'STS\Events\Rating\PendingRate' => [
            'STS\Listeners\Notification\PendingRate',
        ],
        'STS\Events\MessageSend' => [
            'STS\Listeners\Notification\MessageSend',
        ],
    ];

    /**
     * Register any other events for your application.
     *
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
