<?php

namespace STS\Listeners\Subscriptions;

use STS\Events\User\Trip;
use Illuminate\Contracts\Queue\ShouldQueue; 
use STS\Notifications\SubscriptionMatchNotification;
use STS\Repository\SubscriptionsRepository;
use STS\Repository\UserRepository; 
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
    public function handle($event)
    {
        $trip = $event->trip;
        $user = $trip->user;
        $subscriptions =  $this->subRepo->search($user, $trip);
        // console_log($subscriptions);
        foreach ($subscriptions as $s) {
            // \Log::info($trip->to_town . ': ' . $s->user->id . ' - ' . $s->user->name);
            // FIXME
            $notification = new SubscriptionMatchNotification();
            $notification->setAttribute('trip', $trip);
            try {
                $notification->notify($s->user);
            } catch (\Exception $e) {
                \Log::info('Ex: ' . $trip->to_town . ': ' . $s->user->id . ' - ' . $s->user->name);
            }
        }
    }
}
