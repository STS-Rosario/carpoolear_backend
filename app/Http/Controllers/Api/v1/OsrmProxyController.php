<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use STS\Http\Controllers\Controller;

/**
 * OSRM-compatible proxy for Leaflet Routing Machine (browser → our API → OSRM demo / self-hosted).
 * Caches JSON responses; tries optional fallback base URL if primary fails.
 */
class OsrmProxyController extends Controller
{
    public function route(Request $request, string $path): JsonResponse
    {
        if (strlen($path) > 4096) {
            return response()->json([
                'code' => 'InvalidUrl',
                'message' => 'Path too long',
            ], 400);
        }

        if (! str_starts_with($path, 'driving/')) {
            return response()->json([
                'code' => 'InvalidUrl',
                'message' => 'Only driving profile is supported',
            ], 400);
        }

        $query = $request->getQueryString();
        $cacheKey = 'osrm_proxy:v1:' . hash('sha256', $path . '|' . ($query ?? ''));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug('[osrm_proxy] cache HIT', [
                'path_preview' => substr($path, 0, 96),
            ]);

            return response()->json($cached)
                ->header('X-OSRM-Proxy-Cache', 'HIT');
        }

        $upstreamPath = '/route/v1/' . $path . ($query ? '?' . $query : '');
        $json = $this->fetchFromOsrmBases($upstreamPath);

        if ($json === null) {
            Log::warning('[osrm_proxy] all upstream attempts failed', [
                'path_preview' => substr($path, 0, 96),
            ]);

            // 200 so browser XHR parses JSON; Leaflet treats code !== Ok as routing error.
            return response()->json([
                'code' => 'NoRoute',
                'message' => 'Routing service unavailable',
                'routes' => [],
                'waypoints' => [],
            ])->header('X-OSRM-Proxy-Cache', 'MISS')
                ->header('X-OSRM-Proxy-Error', 'upstream_failed');
        }

        $ttlSeconds = (($json['code'] ?? '') === 'Ok')
            ? (int) config('carpoolear.osrm_proxy_cache_ttl_success_seconds', 86400)
            : (int) config('carpoolear.osrm_proxy_cache_ttl_error_seconds', 3600);

        Cache::put($cacheKey, $json, now()->addSeconds(max(60, $ttlSeconds)));

        Log::debug('[osrm_proxy] cache STORE', [
            'path_preview' => substr($path, 0, 96),
            'osrm_code' => $json['code'] ?? null,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return response()->json($json)
            ->header('X-OSRM-Proxy-Cache', 'MISS');
    }

    private function fetchFromOsrmBases(string $upstreamPath): ?array
    {
        $primary = config('carpoolear.osrm_router_base_url');
        $fallback = config('carpoolear.osrm_router_fallback_base_url');
        $bases = array_values(array_unique(array_filter([$primary, $fallback])));

        foreach ($bases as $base) {
            $url = rtrim((string) $base, '/') . $upstreamPath;
            try {
                $response = Http::timeout(45)->get($url);
                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data) && array_key_exists('code', $data)) {
                        Log::info('[osrm_proxy] upstream response', [
                            'base' => $base,
                            'http_status' => $response->status(),
                            'osrm_code' => $data['code'] ?? null,
                        ]);

                        return $data;
                    }
                } else {
                    Log::warning('[osrm_proxy] upstream HTTP not successful', [
                        'base' => $base,
                        'http_status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[osrm_proxy] upstream exception', [
                    'base' => $base,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
