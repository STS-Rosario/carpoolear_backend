<?php

namespace STS\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mapbox Directions API — server-side only, for trip distance/duration when OSRM fails.
 * Not used by the Leaflet OSRM proxy. (GoogleDrivingRouteService is kept for optional future use.)
 */
class MapboxDirectionsRouteService
{
    public function isEnabled(): bool
    {
        return (bool) strlen((string) config('carpoolear.mapbox_access_token', ''));
    }

    /**
     * @param  array<int, array{lat: float|int|string, lng: float|int|string}>  $points
     * @return array{distance: int, duration: int}|null  distance meters, duration whole seconds
     */
    public function drivingDistanceAndDuration(array $points): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $count = count($points);
        if ($count < 2) {
            return null;
        }

        if ($count > 25) {
            Log::warning('[mapbox_directions] too many coordinates (max 25)', ['count' => $count]);

            return null;
        }

        $coordParts = [];
        foreach ($points as $point) {
            $coordParts[] = ((float) $point['lng']).','.((float) $point['lat']);
        }
        $coordinatePath = implode(';', $coordParts);

        $token = (string) config('carpoolear.mapbox_access_token');
        $url = 'https://api.mapbox.com/directions/v5/mapbox/driving/'.$coordinatePath.'.json';

        try {
            $response = Http::timeout(45)->get($url, [
                'overview' => 'false',
                'alternatives' => 'false',
                'access_token' => $token,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[mapbox_directions] request exception', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('[mapbox_directions] HTTP error', [
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 300),
            ]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        if (empty($data['routes'][0]) || ! is_array($data['routes'][0])) {
            Log::info('[mapbox_directions] no route', [
                'code' => $data['code'] ?? null,
                'message' => $data['message'] ?? null,
            ]);

            return null;
        }

        $route = $data['routes'][0];
        $distance = $route['distance'] ?? null;
        $duration = $route['duration'] ?? null;

        if ($distance === null || $duration === null) {
            return null;
        }

        return [
            'distance' => (int) round((float) $distance),
            'duration' => (int) round((float) $duration),
        ];
    }
}
