<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use STS\Http\Controllers\Controller;
use STS\Jobs\SyncArgautosCarCatalogJob;
use STS\Services\Argautos\CarCatalogSyncService;

class CarCatalogSyncController extends Controller
{
    public function store(CarCatalogSyncService $service): JsonResponse
    {
        $status = $service->currentStatus();
        if ($status['running'] ?? false) {
            return response()->json([
                'message' => 'sync_already_running',
            ], Response::HTTP_CONFLICT);
        }

        SyncArgautosCarCatalogJob::dispatch();

        return response()->json(['data' => ['queued' => true]], Response::HTTP_ACCEPTED);
    }

    public function status(CarCatalogSyncService $service): JsonResponse
    {
        return response()->json(['data' => $service->currentStatus()]);
    }
}
