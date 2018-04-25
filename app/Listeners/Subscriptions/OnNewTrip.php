<?php

namespace STS\Listeners\Subscriptions;

use STS\Events\User\Trip;
use STS\Notifications\SubscriptionMatchNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Contracts\Repository\User as UserRepository;
use STS\Contracts\Repository\Subscription as SubscriptionsRepository;

class OnNewTrip implements ShouldQueue
{
    protected $userRepo, $subRepo;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepo, SubscriptionsRepository $subRepo)
    {
        $this->subRepo = $subRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Handle the event.
     *
     * @param Create $event
     *
     * @return void
     */
    public function handle(Create $event)
    {
        $trip = $event->$trip;
        $user = $trip->user;
        $subscriptions =  $this->subRepo->search($user, $trip);
        foreach ($subscriptions as $s) {
            $notification = new SubscriptionMatchNotification();
            $notification->setAttribute('trip', $trip);
            $notification->notify($s->user);
        }
    }
}
