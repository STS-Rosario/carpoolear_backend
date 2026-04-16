<?php

namespace STS\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Routes API v2 (computeRoutes) — server-side trip routing helper.
 * Reserved for future use; TripRepository::getTripInfo currently uses MapboxDirectionsRouteService only.
 * Not used by the Leaflet OSRM proxy.
 */
class GoogleDrivingRouteService
{
    public function isEnabled(): bool
    {
        return (bool) strlen((string) config('carpoolear.google_routes_api_key', ''));
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

        // Routes API: up to 25 intermediates between origin and destination
        if ($count > 27) {
            Log::warning('[google_routes] too many waypoints for Routes API', ['count' => $count]);

            return null;
        }

        $origin = $points[0];
        $destination = $points[$count - 1];
        $intermediates = [];
        for ($i = 1; $i < $count - 1; $i++) {
            $intermediates[] = $this->waypointFromPoint($points[$i]);
        }

        $body = [
            'origin' => $this->waypointFromPoint($origin),
            'destination' => $this->waypointFromPoint($destination),
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_UNAWARE',
        ];
        if ($intermediates !== []) {
            $body['intermediates'] = $intermediates;
        }

        $region = config('carpoolear.google_routes_region_code');
        if (is_string($region) && $region !== '') {
            $body['regionCode'] = $region;
        }

        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'X-Goog-Api-Key' => (string) config('carpoolear.google_routes_api_key'),
                    'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://routes.googleapis.com/directions/v2:computeRoutes', $body);
        } catch (\Throwable $e) {
            Log::warning('[google_routes] request exception', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('[google_routes] HTTP error', [
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 300),
            ]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['routes'][0])) {
            Log::info('[google_routes] no routes in response');

            return null;
        }

        $route = $data['routes'][0];
        $distance = $route['distanceMeters'] ?? null;
        $durationRaw = $route['duration'] ?? null;

        if ($distance === null || $durationRaw === null) {
            return null;
        }

        $durationSeconds = $this->parseDurationSeconds((string) $durationRaw);
        if ($durationSeconds === null) {
            return null;
        }

        return [
            'distance' => (int) $distance,
            'duration' => (int) round($durationSeconds),
        ];
    }

    private function waypointFromPoint(array $point): array
    {
        return [
            'location' => [
                'latLng' => [
                    'latitude' => (float) $point['lat'],
                    'longitude' => (float) $point['lng'],
                ],
            ],
        ];
    }

    private function parseDurationSeconds(string $duration): ?float
    {
        $duration = trim($duration);
        if ($duration === '' || ! str_ends_with($duration, 's')) {
            return null;
        }

        $num = substr($duration, 0, -1);

        return is_numeric($num) ? (float) $num : null;
    }
}
