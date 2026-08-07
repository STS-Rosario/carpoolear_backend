<?php

namespace STS\Listeners\Subscriptions;

use Illuminate\Contracts\Queue\ShouldQueue;
use STS\Events\Trip\Create;
use STS\Models\Trip;
use STS\Notifications\SubscriptionMatchNotification;
use STS\Repository\SubscriptionsRepository;
use STS\Repository\UserRepository;
use STS\Services\Notifications\Models\DatabaseNotification;

class OnNewTrip implements ShouldQueue
{
    protected $userRepo;

    protected $subRepo;

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
     * @return void
     */
    public function handle(Create $event)
    {
        $trip = $event->trip;
        $user = $trip->user;
        $subscriptions = $this->subRepo->search($user, $trip);
        // console_log($subscriptions);
        foreach ($subscriptions as $s) {
            // \Log::info($trip->to_town . ': ' . $s->user->id . ' - ' . $s->user->name);
            // FIXME
            if ($this->alreadyNotified($s->user->id, $trip->id)) {
                continue;
            }

            $notification = new SubscriptionMatchNotification;
            $notification->setAttribute('trip', $trip);
            try {
                $notification->notify($s->user);
            } catch (\Exception $e) {
                \Log::warning('Subscription notification failed', ['trip_id' => $trip->id, 'user_id' => $s->user->id]);
            }
        }
    }

    private function alreadyNotified(int $userId, int $tripId): bool
    {
        return DatabaseNotification::query()
            ->where('user_id', $userId)
            ->where('type', SubscriptionMatchNotification::class)
            ->whereNull('deleted_at')
            ->whereHas('plain_values', function ($query) use ($tripId) {
                $query->where('key', 'trip')
                    ->where('value_type', Trip::class)
                    ->where('value_id', $tripId);
            })
            ->exists();
    }
}
