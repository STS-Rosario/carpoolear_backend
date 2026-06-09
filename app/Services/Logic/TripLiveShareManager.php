<?php

namespace STS\Services\Logic;

use Carbon\Carbon;
use STS\Helpers\OngoingTripHelper;
use STS\Models\Trip;
use STS\Models\TripLiveShare;
use STS\Models\User;
use STS\Notifications\DriverLiveLocationSharingNotification;
use STS\Notifications\LiveLocationAutoStoppedNotification;
use STS\Notifications\LiveLocationStopReminderNotification;
use STS\Repository\TripLiveShareRepository;
use STS\Repository\TripRepository;

class TripLiveShareManager extends BaseManager
{
    public function __construct(
        protected TripLiveShareRepository $liveShareRepo,
        protected TripRepository $tripRepo,
        protected TripsManager $tripsManager
    ) {}

    public function start(User $user, int $tripId): ?TripLiveShare
    {
        $trip = $this->resolveTrip($tripId);
        if (! $trip || ! $this->isParticipant($user, $trip)) {
            $this->setErrors(['error' => 'access_denied']);

            return null;
        }

        if (! OngoingTripHelper::canStartSharing(Carbon::now(), $trip->trip_date, $trip->estimated_time)) {
            $this->setErrors(['error' => 'sharing_not_available']);

            return null;
        }

        $wasActive = ($existing = $this->liveShareRepo->findByTripAndUser($tripId, $user->id)) && $existing->is_active;
        $share = $this->liveShareRepo->start($tripId, $user->id);

        if (! $wasActive && $this->shouldNotifyParticipantsOfSharing($user, $trip)) {
            $this->notifyPassengersOfDriverSharing($trip, $user);
        }

        return $share;
    }

    public function updateLocation(User $user, int $tripId, float $lat, float $lng): ?TripLiveShare
    {
        $trip = $this->resolveTrip($tripId);
        if (! $trip) {
            $this->setErrors(['error' => 'trip_not_found']);

            return null;
        }

        $share = $this->liveShareRepo->findByTripAndUser($tripId, $user->id);
        if (! $share || ! $share->is_active) {
            $this->setErrors(['error' => 'share_not_active']);

            return null;
        }

        if ($this->isPastAutoStop($trip, $share)) {
            $this->setErrors(['error' => 'sharing_expired']);

            return null;
        }

        return $this->liveShareRepo->updateLocation($share, $lat, $lng);
    }

    public function stop(User $user, int $tripId): ?TripLiveShare
    {
        $share = $this->liveShareRepo->findByTripAndUser($tripId, $user->id);
        if (! $share || ! $share->is_active) {
            $this->setErrors(['error' => 'share_not_active']);

            return null;
        }

        return $this->liveShareRepo->stop($share);
    }

    public function getStatus(User $user, int $tripId): ?TripLiveShare
    {
        $trip = $this->resolveTrip($tripId);
        if (! $trip || ! $this->isParticipant($user, $trip)) {
            $this->setErrors(['error' => 'access_denied']);

            return null;
        }

        return $this->liveShareRepo->findByTripAndUser($tripId, $user->id);
    }

    public function getPublicView(string $token): ?array
    {
        $share = $this->liveShareRepo->findByToken($token);
        if (! $share) {
            return null;
        }

        $trip = $share->trip;
        $driver = $trip->user;

        return [
            'is_active' => (bool) $share->is_active,
            'is_passenger_share' => ! $this->isDriverShare($share, $trip),
            'lat' => $share->lat,
            'lng' => $share->lng,
            'recorded_at' => $share->recorded_at?->toIso8601String(),
            'destination' => $trip->to_town,
            'trip_date' => $trip->trip_date?->toIso8601String(),
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'image' => $driver->image,
                'positive_ratings' => (int) $driver->positive_ratings,
                'negative_ratings' => (int) $driver->negative_ratings,
            ],
            'sharer' => [
                'id' => $share->user->id,
                'name' => $share->user->name,
            ],
        ];
    }

    public function getTripView(User $user, int $tripId): ?array
    {
        $trip = $this->resolveTrip($tripId);
        if (! $trip || ! $this->isParticipant($user, $trip)) {
            $this->setErrors(['error' => 'access_denied']);

            return null;
        }

        $share = $this->liveShareRepo->findDriverShare($tripId)
            ?? $this->liveShareRepo->findLatestShareForTrip($tripId);
        if (! $share) {
            return null;
        }

        return [
            'is_active' => (bool) $share->is_active,
            'lat' => $share->lat,
            'lng' => $share->lng,
            'recorded_at' => $share->recorded_at?->toIso8601String(),
            'sharer' => [
                'id' => $share->user->id,
                'name' => $share->user->name,
            ],
        ];
    }

    public function isDriverShare(TripLiveShare $share, Trip $trip): bool
    {
        return (int) $share->user_id === (int) $trip->user_id;
    }

    public function processActiveShares(): void
    {
        $shares = $this->liveShareRepo->getActiveSharesForProcessing();

        foreach ($shares as $share) {
            $trip = $share->trip;
            if (! $trip) {
                continue;
            }

            if ($this->shouldAutoStop($trip, $share)) {
                $this->liveShareRepo->autoStop($share);
                $this->notifyAutoStopped($share->user, $trip);

                continue;
            }

            if ($this->shouldSendStopReminder($trip, $share)) {
                $this->notifyStopReminder($share->user, $trip);
                $this->liveShareRepo->markStopReminderSent($share);
            }
        }
    }

    private function shouldNotifyParticipantsOfSharing(User $user, Trip $trip): bool
    {
        return (int) $trip->user_id === (int) $user->id;
    }

    private function notifyPassengersOfDriverSharing(Trip $trip, User $driver): void
    {
        $trip->loadMissing('passengerAccepted.user');
        $sharerId = (int) $driver->id;
        $tripOwnerId = (int) $trip->user_id;

        foreach ($trip->passengerAccepted as $passenger) {
            if (! $passenger->user) {
                continue;
            }

            $passengerUserId = (int) $passenger->user_id;
            if ($passengerUserId === $sharerId || $passengerUserId === $tripOwnerId) {
                continue;
            }

            $notification = new DriverLiveLocationSharingNotification;
            $notification->setAttribute('trip', $trip);
            $notification->setAttribute('from', $driver);
            $notification->notify($passenger->user);
        }
    }

    private function notifyStopReminder(User $user, Trip $trip): void
    {
        $notification = new LiveLocationStopReminderNotification;
        $notification->setAttribute('trip', $trip);
        $notification->notify($user);
    }

    private function notifyAutoStopped(User $user, Trip $trip): void
    {
        $notification = new LiveLocationAutoStoppedNotification;
        $notification->setAttribute('trip', $trip);
        $notification->notify($user);
    }

    private function shouldAutoStop(Trip $trip, TripLiveShare $share): bool
    {
        $autoStopAt = OngoingTripHelper::getAutoStopAtForShare(
            $trip->trip_date,
            $trip->estimated_time,
            $share->started_at
        );

        return Carbon::now()->greaterThanOrEqualTo($autoStopAt);
    }

    private function shouldSendStopReminder(Trip $trip, TripLiveShare $share): bool
    {
        if ($share->stop_reminder_sent_at || $share->lat === null || $share->lng === null) {
            return false;
        }

        $destination = $trip->points->sortBy('id')->last();
        if (! $destination) {
            return false;
        }

        return OngoingTripHelper::shouldSendStopReminder(
            Carbon::now(),
            $trip->trip_date,
            $trip->estimated_time,
            (float) $share->lat,
            (float) $share->lng,
            (float) $destination->lat,
            (float) $destination->lng
        );
    }

    private function resolveTrip(int $tripId): ?Trip
    {
        return Trip::query()->with(['passengerAccepted', 'points', 'user'])->find($tripId);
    }

    private function isParticipant(User $user, Trip $trip): bool
    {
        return $this->tripsManager->tripOwner($user, $trip) || $trip->isPassenger($user);
    }

    private function isPastAutoStop(Trip $trip, TripLiveShare $share): bool
    {
        $autoStopAt = OngoingTripHelper::getAutoStopAtForShare(
            $trip->trip_date,
            $trip->estimated_time,
            $share->started_at
        );

        return Carbon::now()->greaterThan($autoStopAt);
    }
}
