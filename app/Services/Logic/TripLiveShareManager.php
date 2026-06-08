<?php

namespace STS\Services\Logic;

use Carbon\Carbon;
use STS\Helpers\OngoingTripHelper;
use STS\Models\Trip;
use STS\Models\TripLiveShare;
use STS\Models\User;
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

        return $this->liveShareRepo->start($tripId, $user->id);
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

        if ($this->isPastAutoStop($trip)) {
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
        $share = $this->liveShareRepo->findActiveByToken($token);
        if (! $share || $share->lat === null || $share->lng === null) {
            return null;
        }

        $trip = $share->trip;
        $driver = $trip->user;

        return [
            'lat' => $share->lat,
            'lng' => $share->lng,
            'recorded_at' => $share->recorded_at?->toIso8601String(),
            'destination' => $trip->to_town,
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

        $share = $this->liveShareRepo->findActiveDriverShare($tripId);
        if (! $share || $share->lat === null || $share->lng === null) {
            return null;
        }

        return [
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

    private function resolveTrip(int $tripId): ?Trip
    {
        return Trip::query()->with(['passengerAccepted', 'points', 'user'])->find($tripId);
    }

    private function isParticipant(User $user, Trip $trip): bool
    {
        return $this->tripsManager->tripOwner($user, $trip) || $trip->isPassenger($user);
    }

    private function isPastAutoStop(Trip $trip): bool
    {
        $autoStopAt = OngoingTripHelper::getAutoStopAt($trip->trip_date, $trip->estimated_time);

        return Carbon::now()->greaterThan($autoStopAt);
    }
}
