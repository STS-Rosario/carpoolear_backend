<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\IdentityVerificationReportService;

class IdentityVerificationReportController extends Controller
{
    /**
     * GET /api/admin/identity-verification-report - monthly (or weekly/daily) verification outcomes and MP fall-through funnel.
     * Contract: docs/identity-verification-report.md
     */
    public function show(Request $request, IdentityVerificationReportService $report): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'group_by' => 'nullable|in:month,week,day',
            'method' => 'nullable|in:all,manual,mercado_pago',
            'surface' => 'nullable|string|max:64',
            'platform' => 'nullable|string|max:32',
            'app_version' => 'nullable|string|max:64',
        ]);

        return response()->json($report->build($validated));
    }
}
