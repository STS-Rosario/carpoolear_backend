<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use STS\Http\Controllers\Controller;
use STS\Models\ManualIdentityValidation;

class AdminDashboardController extends Controller
{
    public function show(): JsonResponse
    {
        $manualIdentityValidations = ManualIdentityValidation::query()
            ->with('user:id,name')
            ->readyForAdminReview()
            ->orderByRaw('COALESCE(submitted_at, paid_at, created_at) ASC')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (ManualIdentityValidation $item) => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'user_name' => $item->user?->name,
                'submitted_at' => $item->submitted_at?->toDateTimeString(),
                'paid_at' => $item->paid_at?->toDateTimeString(),
            ]);

        return response()->json([
            'data' => [
                'manual_identity_validations' => $manualIdentityValidations,
            ],
        ]);
    }
}
