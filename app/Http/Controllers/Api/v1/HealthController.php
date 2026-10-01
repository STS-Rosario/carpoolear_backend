<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use STS\Http\Controllers\Controller;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (\Throwable) {
            return response()->json([
                'status' => 'degraded',
                'database' => 'down',
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'database' => 'up',
        ]);
    }
}
