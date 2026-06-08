<?php

namespace STS\Repository;

use Carbon\Carbon;
use Illuminate\Support\Str;
use STS\Models\TripLiveShare;

class TripLiveShareRepository
{
    public function findByTripAndUser(int $tripId, int $userId): ?TripLiveShare
    {
        return TripLiveShare::query()
            ->where('trip_id', $tripId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findActiveByToken(string $token): ?TripLiveShare
    {
        return TripLiveShare::query()
            ->where('share_token', $token)
            ->where('is_active', true)
            ->with(['trip.user', 'trip.points', 'user'])
            ->first();
    }

    public function findActiveDriverShare(int $tripId): ?TripLiveShare
    {
        return TripLiveShare::query()
            ->where('trip_id', $tripId)
            ->where('is_active', true)
            ->whereHas('trip', fn ($q) => $q->whereColumn('trips.user_id', 'trip_live_shares.user_id'))
            ->with(['user'])
            ->first();
    }

    public function start(int $tripId, int $userId): TripLiveShare
    {
        $existing = $this->findByTripAndUser($tripId, $userId);

        if ($existing) {
            $existing->update([
                'is_active' => true,
                'started_at' => Carbon::now(),
                'stopped_at' => null,
                'auto_stopped_at' => null,
                'stop_reminder_sent_at' => null,
            ]);

            return $existing->fresh();
        }

        return TripLiveShare::create([
            'trip_id' => $tripId,
            'user_id' => $userId,
            'share_token' => Str::random(48),
            'is_active' => true,
            'started_at' => Carbon::now(),
        ]);
    }

    public function updateLocation(TripLiveShare $share, float $lat, float $lng): TripLiveShare
    {
        $share->update([
            'lat' => $lat,
            'lng' => $lng,
            'recorded_at' => Carbon::now(),
        ]);

        return $share->fresh();
    }

    public function stop(TripLiveShare $share): TripLiveShare
    {
        $share->update([
            'is_active' => false,
            'lat' => null,
            'lng' => null,
            'recorded_at' => null,
            'stopped_at' => Carbon::now(),
        ]);

        return $share->fresh();
    }

    public function getActiveSharesForProcessing(): \Illuminate\Database\Eloquent\Collection
    {
        return TripLiveShare::query()
            ->where('is_active', true)
            ->with(['trip.points', 'user'])
            ->get();
    }
}
