<?php

namespace STS\Listeners\Subscriptions;

use STS\Events\User\Trip;
// use STS\Notifications\NewUserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Contracts\Repository\User as UserRepository;
use STS\Contracts\Logic\Subscription as SubscriptionsManager;

class OnNewTrip implements ShouldQueue
{
    protected $userRepo, $sManager;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepo, SubscriptionsManager $manager)
    {
        $this->sManager = $manager;
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
        // $user = $this->userRepo->show($event->id);
        // if ($user && $user->email) {
        //     $notification = new NewUserNotification();
        //     $notification->notify($user);
        // }
    }
}
