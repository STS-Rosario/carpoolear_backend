<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\IdentityVerificationStatsService;

class IdentityVerificationStatsController extends Controller
{
    public function show(Request $request, IdentityVerificationStatsService $stats): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'method' => 'required|in:mercado_pago,manual',
            'group_by' => 'nullable|in:day,week',
            'surface' => 'nullable|string|max:64',
            'platform' => 'nullable|string|max:32',
            'app_version' => 'nullable|string|max:64',
        ]);

        return response()->json([
            'data' => $stats->summarize($validated),
        ]);
    }
}
