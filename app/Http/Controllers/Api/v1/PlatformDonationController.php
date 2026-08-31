<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscription;
use STS\Services\PlatformDonationService;

class PlatformDonationController extends Controller
{
    public function __construct(private PlatformDonationService $platformDonationService)
    {
        $this->middleware('logged');
    }

    public function checkoutOnce(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'tier_id' => 'nullable|integer|exists:donation_tiers,id',
            'amount' => 'nullable|integer|min:1',
            'source' => 'nullable|string|max:64',
            'trip_id' => 'nullable|integer',
        ]);

        if (empty($validated['tier_id']) && empty($validated['amount'])) {
            return response()->json(['error' => 'tier_id or amount is required'], 422);
        }

        $result = $this->platformDonationService->checkoutOnce($request->user(), $validated);

        return response()->json($result);
    }

    public function checkoutMonthly(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'tier_id' => 'nullable|integer|exists:donation_tiers,id',
            'amount' => 'nullable|integer|min:1',
            'source' => 'nullable|string|max:64',
            'trip_id' => 'nullable|integer',
        ]);

        if (empty($validated['tier_id']) && empty($validated['amount'])) {
            return response()->json(['error' => 'tier_id or amount is required'], 422);
        }

        $result = $this->platformDonationService->checkoutMonthly($request->user(), $validated);

        return response()->json($result);
    }

    public function myDonations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $payments = DonationPayment::query()
            ->with('tier')
            ->where('user_id', $userId)
            ->latest()
            ->limit(50)
            ->get();

        $subscription = DonationSubscription::query()
            ->with('tier')
            ->where('user_id', $userId)
            ->latest()
            ->first();

        return response()->json([
            'payments' => $payments,
            'subscription' => $subscription,
        ]);
    }

    private function ensureEnabled(): void
    {
        if (! $this->platformDonationService->isEnabled()) {
            abort(503, 'Platform donations API is disabled');
        }
    }
}
