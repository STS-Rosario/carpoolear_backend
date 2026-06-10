<?php

namespace STS\Services;

use Carbon\Carbon;
use STS\Models\Trip;
use STS\Notifications\FriendCreatedTripNotification;
use STS\Repository\FriendTripAlertRepository;
use STS\Services\Logic\TripsManager;

class FriendTripAlertService
{
    protected FriendTripAlertRepository $alertRepo;

    protected TripsManager $tripsManager;

    public function __construct(FriendTripAlertRepository $alertRepo, TripsManager $tripsManager)
    {
        $this->alertRepo = $alertRepo;
        $this->tripsManager = $tripsManager;
    }

    public function notifyIfVisible(Trip $trip): void
    {
        if ($trip->friend_trip_alert_sent_at) {
            return;
        }

        $driver = $trip->user;
        $subscribers = $this->alertRepo->getSubscribersForDriver($driver);

        if ($subscribers->isEmpty()) {
            return;
        }

        $notified = false;

        foreach ($subscribers as $subscriber) {
            if (! $this->tripsManager->userCanSeeTrip($subscriber, $trip)) {
                continue;
            }

            $notification = new FriendCreatedTripNotification;
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('driver', $driver);
            $notification->notify($subscriber);
            $notified = true;
        }

        if ($notified) {
            $trip->friend_trip_alert_sent_at = Carbon::now();
            $trip->save();
        }
    }

    public function isImmediatelyVisible(Trip $trip): bool
    {
        if ($trip->needs_sellado && $trip->state !== Trip::STATE_READY) {
            return false;
        }

        return true;
    }
}
